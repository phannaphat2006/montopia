<?php

namespace App\Services;

use App\Models\ProjectAttachment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;

class BackupBundleService
{
    public function create(): string
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('ชุดสำรองทั้งหมดรองรับ MySQL เท่านั้น');
        }
        $directory = $this->newDirectory(storage_path('app/private/backups'), 'bundle_');
        $pdo = DB::connection()->getPdo();
        $tables = $this->fingerprints($pdo);
        $attachments = ProjectAttachment::query()->orderBy('id')->get();
        if (Artisan::call('monstopia:backup-database', ['--directory' => $directory]) !== 0) {
            throw new RuntimeException('สำรอง SQL ไม่สำเร็จ ชุดนี้ยังไม่สมบูรณ์');
        }
        $database = File::glob($directory.DIRECTORY_SEPARATOR.'*.sql.gz')[0] ?? null;
        if (! $database) {
            throw new RuntimeException('ไม่พบไฟล์ SQL สำรอง');
        }
        $files = [];
        foreach ($attachments as $attachment) {
            $path = $this->relativePath($attachment->getRawOriginal('stored_path'));
            if (! preg_match('/\A[a-zA-Z0-9_-]+\z/', $attachment->disk)) {
                throw new RuntimeException('ชื่อ disk ของไฟล์ไม่ถูกต้อง');
            }
            $backupPath = 'files/'.$attachment->id.'.bin';
            $input = Storage::disk($attachment->disk)->readStream($path);
            if (! is_resource($input)) {
                throw new RuntimeException('ไฟล์แนบ #'.$attachment->id.' หายไป สำรองไม่ครบ');
            }
            File::ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.'files', 0700);
            try {
                $this->copyStream($input, $directory.DIRECTORY_SEPARATOR.$backupPath);
            } finally {
                fclose($input);
            }
            $destination = $directory.DIRECTORY_SEPARATOR.$backupPath;
            if (filesize($destination) !== $attachment->size_bytes) {
                throw new RuntimeException('ขนาดไฟล์แนบ #'.$attachment->id.' ไม่ตรงฐานข้อมูล');
            }
            $files[] = [
                'attachment_id' => $attachment->id,
                'disk' => $attachment->disk,
                'stored_path' => $path,
                'backup_path' => $backupPath,
                'size_bytes' => filesize($destination),
                'sha256' => hash_file('sha256', $destination),
            ];
        }
        if ($tables !== $this->fingerprints($pdo)) {
            throw new RuntimeException('ข้อมูลเปลี่ยนระหว่างสำรอง กรุณาสำรองอีกครั้งในช่วงที่ไม่มีการแก้ไข');
        }
        $manifest = [
            'version' => 1,
            'status' => 'complete',
            'created_at' => now()->toIso8601String(),
            'database' => [
                'name' => config('database.connections.mysql.database'),
                'server_uuid' => $this->serverUuid($pdo),
                'backup_path' => basename($database),
                'size_bytes' => filesize($database),
                'sha256' => hash_file('sha256', $database),
                'tables' => $tables,
            ],
            'files' => $files,
        ];
        $this->writeJson($directory.DIRECTORY_SEPARATOR.'manifest.json', $manifest);
        $external = config('monstopia.backup_external_directory');
        if ($external) {
            $this->copyBundle($directory, (string) $external);
        }

        return $directory;
    }

    public function verify(string $directory, bool $filesOnly = false): array
    {
        $directory = realpath($directory) ?: throw new RuntimeException('ไม่พบโฟลเดอร์ชุดสำรอง');
        $manifestPath = $this->checkedSource($directory, 'manifest.json');
        $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['version'] ?? null) !== 1 || ($manifest['status'] ?? null) !== 'complete' || ! is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('Manifest ไม่ใช่ชุดสำรองที่สมบูรณ์');
        }
        $database = $this->verifiedFile($directory, $manifest['database']);
        $restoreRoot = $this->newDirectory(storage_path('app/private/restore-tests'), 'restore_qa_');
        $restored = [];
        foreach ($manifest['files'] as $file) {
            $source = $this->verifiedFile($directory, $file);
            $disk = (string) ($file['disk'] ?? '');
            if (! preg_match('/\A[a-zA-Z0-9_-]+\z/', $disk)) {
                throw new RuntimeException('ชื่อ disk ใน manifest ไม่ถูกต้อง');
            }
            $target = $restoreRoot.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$disk.DIRECTORY_SEPARATOR.$this->relativePath($file['stored_path']);
            File::ensureDirectoryExists(dirname($target), 0700);
            $input = fopen($source, 'rb');
            try {
                $this->copyStream($input, $target);
            } finally {
                fclose($input);
            }
            if (hash_file('sha256', $target) !== $file['sha256']) {
                throw new RuntimeException('ไฟล์ที่กู้คืนมี checksum ไม่ตรง');
            }
            $restored[] = ['attachment_id' => $file['attachment_id'], 'sha256' => $file['sha256']];
        }
        $databaseName = $filesOnly ? null : $this->restoreDatabase($database, $manifest['database']);
        $result = [
            'verified_at' => now()->toIso8601String(),
            'mode' => $filesOnly ? 'files-only' : 'database-and-files',
            'source_bundle' => $directory,
            'restore_directory' => $restoreRoot,
            'restore_database' => $databaseName,
            'database_sha256' => $manifest['database']['sha256'],
            'files_verified' => count($restored),
            'tables_verified' => $filesOnly ? 0 : count($manifest['database']['tables']),
        ];
        $this->writeJson($restoreRoot.DIRECTORY_SEPARATOR.'verification.json', $result);

        return $result;
    }

    public function fingerprints(PDO $pdo): array
    {
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);
        $result = [];
        foreach ($tables as $table) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $definition = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
            // MySQL may omit a redundant column CHARACTER SET when re-importing.
            $definition = preg_replace('/ CHARACTER SET utf8mb4(?= COLLATE utf8mb4_unicode_ci)/', '', $definition);
            $columns = $pdo->query('SHOW COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
            $primary = array_column(array_filter($columns, fn ($column) => $column['Key'] === 'PRI'), 'Field');
            $order = $primary ?: array_column($columns, 'Field');
            $order = implode(', ', array_map(fn ($column) => '`'.str_replace('`', '``', $column).'`', $order));
            $statement = $pdo->query('SELECT * FROM '.$quoted.' ORDER BY '.$order);
            $hash = hash_init('sha256');
            $count = 0;
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                hash_update($hash, json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
            $result[$table] = ['rows' => $count, 'data_sha256' => hash_final($hash), 'schema_sha256' => hash('sha256', $definition)];
        }

        return $result;
    }

    private function restoreDatabase(string $gzip, array $database): string
    {
        $host = config('monstopia.restore_host');
        $port = config('monstopia.restore_port');
        $user = config('monstopia.restore_username');
        if (! $host || ! $port || ! $user) {
            throw new RuntimeException('ต้องตั้งค่า MONSTOPIA_RESTORE_HOST/PORT/USERNAME/PASSWORD ของ MySQL ทดสอบแยกก่อน หรือใช้ --files-only');
        }
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, config('monstopia.restore_password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $uuid = $this->serverUuid($pdo);
        if ($uuid === ($database['server_uuid'] ?? null) || $uuid === $this->serverUuid(DB::connection()->getPdo())) {
            throw new RuntimeException('ปฏิเสธการกู้คืนบน MySQL server เดียวกับระบบจริง ต้องใช้ server ทดสอบแยก');
        }
        $name = 'monstopia_restore_qa_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
        $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `'.$name.'`');
        $input = gzopen($gzip, 'rb');
        if (! $input) {
            throw new RuntimeException('อ่าน SQL gzip ไม่ได้');
        }
        try {
            $sql = stream_get_contents($input);
        } finally {
            gzclose($input);
        }
        if (preg_match('/^\s*(?:USE\s|(?:CREATE|DROP|ALTER)\s+DATABASE\s|SET\s+(?:@@)?GLOBAL|DELIMITER\s|LOAD\s+DATA\s)/im', $sql)) {
            throw new RuntimeException('SQL มีคำสั่งระดับ server หรือเปลี่ยนฐานข้อมูล คำสั่งนี้ไม่อนุญาต');
        }
        $pdo->exec($sql);
        if ($this->fingerprints($pdo) !== $database['tables']) {
            throw new RuntimeException('ข้อมูลหรือโครงสร้างฐานข้อมูลที่กู้คืนไม่ตรงกับ manifest');
        }

        return $name;
    }

    private function serverUuid(PDO $pdo): string
    {
        $uuid = $pdo->query('SELECT @@server_uuid')->fetchColumn();
        if (! is_string($uuid) || $uuid === '') {
            throw new RuntimeException('ตรวจเอกลักษณ์ MySQL server ไม่ได้');
        }

        return $uuid;
    }

    private function copyBundle(string $source, string $parent): void
    {
        // The operator chooses this path. No cloud account or credentials are assumed.
        $target = $this->newDirectory($parent, basename($source).'_');
        foreach (File::allFiles($source) as $file) {
            if ($file->getRelativePathname() === 'manifest.json') {
                continue;
            }
            $relative = $this->relativePath($file->getRelativePathname());
            $destination = $target.DIRECTORY_SEPARATOR.$relative;
            File::ensureDirectoryExists(dirname($destination), 0700);
            if (! File::copy($file->getPathname(), $destination)) {
                throw new RuntimeException('คัดลอกชุดสำรองภายนอกไม่สำเร็จ');
            }
            if (hash_file('sha256', $destination) !== hash_file('sha256', $file->getPathname())) {
                throw new RuntimeException('Checksum ชุดสำรองนอกเครื่องไม่ตรง');
            }
        }
        if (! File::copy($source.DIRECTORY_SEPARATOR.'manifest.json', $target.DIRECTORY_SEPARATOR.'manifest.json')) {
            throw new RuntimeException('คัดลอก manifest ภายนอกไม่สำเร็จ');
        }
        if (hash_file('sha256', $source.DIRECTORY_SEPARATOR.'manifest.json') !== hash_file('sha256', $target.DIRECTORY_SEPARATOR.'manifest.json')) {
            throw new RuntimeException('Checksum manifest ของชุดสำรองภายนอกไม่ตรง');
        }
    }

    private function newDirectory(string $parent, string $prefix): string
    {
        if (! str_starts_with($parent, '/') && ! preg_match('/\A[A-Za-z]:[\\\\\/]/', $parent)) {
            throw new RuntimeException('ปลายทางสำรองต้องเป็น absolute path');
        }
        File::ensureDirectoryExists($parent, 0700);
        $resolved = realpath($parent);
        $public = realpath(public_path());
        if (! $resolved || ! $public || str_starts_with(strtolower(str_replace('\\', '/', $resolved)).'/', strtolower(str_replace('\\', '/', $public)).'/')) {
            throw new RuntimeException('ต้องเก็บชุดสำรองและไฟล์กู้คืนไว้นอก public');
        }
        $directory = $resolved.DIRECTORY_SEPARATOR.$prefix.gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('สร้างโฟลเดอร์ใหม่สำหรับสำรองไม่ได้');
        }

        return $directory;
    }

    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || str_contains($path, ':') || in_array('..', explode('/', $path), true)) {
            throw new RuntimeException('เส้นทางไฟล์ไม่ปลอดภัย');
        }

        return $path;
    }

    private function checkedSource(string $directory, string $relative): string
    {
        $source = $directory.DIRECTORY_SEPARATOR.$this->relativePath($relative);
        $resolved = realpath($source);
        if (! $resolved || ! is_file($resolved) || is_link($source) || ! str_starts_with($resolved, $directory.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('ไฟล์สำรองหายไปหรือเส้นทางอยู่นอกชุดสำรอง');
        }

        return $resolved;
    }

    private function verifiedFile(string $directory, array $file): string
    {
        $source = $this->checkedSource($directory, $file['backup_path'] ?? '');
        if (filesize($source) !== ($file['size_bytes'] ?? null) || ! hash_equals($file['sha256'] ?? '', hash_file('sha256', $source))) {
            throw new RuntimeException('ขนาดหรือ checksum SHA-256 ของชุดสำรองไม่ตรง');
        }

        return $source;
    }

    private function copyStream($input, string $destination): void
    {
        $output = fopen($destination, 'xb');
        if (! is_resource($input) || ! $output) {
            throw new RuntimeException('สร้างไฟล์สำรองใหม่ไม่ได้');
        }
        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('คัดลอกไฟล์ไม่สำเร็จ');
            }
            if (! fflush($output)) {
                throw new RuntimeException('บันทึกไฟล์สำรองไม่ครบ กรุณาตรวจพื้นที่ดิสก์');
            }
        } finally {
            fclose($output);
        }
        @chmod($destination, 0600);
    }

    private function writeJson(string $destination, array $data): void
    {
        if (File::exists($destination)) {
            throw new RuntimeException('ไม่อนุญาตให้เขียนทับ metadata ของชุดสำรองเดิม');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $destination.'.'.bin2hex(random_bytes(6)).'.tmp';
        try {
            if (File::put($temporary, $json) !== strlen($json)) {
                throw new RuntimeException('บันทึก JSON ของชุดสำรอง/ผลกู้คืนไม่ครบ กรุณาตรวจพื้นที่ดิสก์');
            }
            @chmod($temporary, 0600);
            if (! File::move($temporary, $destination)) {
                throw new RuntimeException('สร้าง metadata JSON ที่สมบูรณ์ไม่สำเร็จ');
            }
        } finally {
            // Only this uniquely generated temporary file, never old backup data.
            File::delete($temporary);
        }
    }
}

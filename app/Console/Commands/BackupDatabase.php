<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'monstopia:backup-database {--directory= : Absolute private output directory} {--prune : Explicitly remove expired SQL-only backups}';

    protected $description = 'Create a compressed MySQL backup in private storage';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('คำสั่งสำรองข้อมูลนี้รองรับฐานข้อมูล MySQL เท่านั้น');

            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $backupDir = $this->option('directory') ?: storage_path('app/private/backups');
        File::ensureDirectoryExists($backupDir);
        $resolvedDirectory = realpath($backupDir);
        $publicDirectory = realpath(public_path());
        if (! $resolvedDirectory || ! $publicDirectory || str_starts_with(strtolower(str_replace('\\', '/', $resolvedDirectory)).'/', strtolower(str_replace('\\', '/', $publicDirectory)).'/')) {
            $this->error('ต้องเก็บชุดสำรองไว้นอกโฟลเดอร์ public เท่านั้น');

            return self::FAILURE;
        }
        $stamp = now()->format('Y-m-d_H-i-s').'_'.bin2hex(random_bytes(4));
        $sqlPath = $backupDir.DIRECTORY_SEPARATOR."monstopia_{$stamp}.sql";
        $gzipPath = $sqlPath.'.gz';
        $credentialsPath = storage_path('framework'.DIRECTORY_SEPARATOR.'mysql-backup-'.bin2hex(random_bytes(8)).'.cnf');

        try {
            File::put($credentialsPath, $this->credentialsFile($connection));
            @chmod($credentialsPath, 0600);
            $binary = $this->resolveBinary((string) config('monstopia.mysqldump_binary'));
            $process = new Process([
                $binary,
                '--defaults-extra-file='.str_replace('\\', '/', $credentialsPath),
                '--host='.$connection['host'],
                '--port='.(string) $connection['port'],
                '--default-character-set=utf8mb4',
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--set-gtid-purged=OFF',
                '--result-file='.str_replace('\\', '/', $sqlPath),
                (string) $connection['database'],
            ]);
            try {
                $process->setTimeout(300)->mustRun();
            } catch (Throwable) {
                File::delete($sqlPath);
                $this->warn('mysqldump ของเครื่องไม่รองรับการเชื่อมต่อ กำลังใช้ตัวสำรองข้อมูลของ Laravel แทน');
                $this->portableDump($sqlPath);
            }
            $this->compress($sqlPath, $gzipPath);
            File::delete($sqlPath);
            if ($this->option('prune')) {
                $this->deleteExpiredBackups($backupDir);
            }
        } catch (Throwable $error) {
            File::delete([$sqlPath, $gzipPath]);
            $this->error('สำรองฐานข้อมูลไม่สำเร็จ: '.$error->getMessage());

            return self::FAILURE;
        } finally {
            File::delete($credentialsPath);
        }

        $this->info('สำรองฐานข้อมูลเรียบร้อย: '.$gzipPath);

        return self::SUCCESS;
    }

    private function credentialsFile(array $connection): string
    {
        $escape = fn ($value) => str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);

        return "[client]\nuser=\"{$escape($connection['username'])}\"\npassword=\"{$escape($connection['password'])}\"\n";
    }

    private function resolveBinary(string $configured): string
    {
        if (is_file($configured)) {
            return $configured;
        }
        $xampp = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

        return PHP_OS_FAMILY === 'Windows' && is_file($xampp) ? $xampp : $configured;
    }

    private function compress(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');
        if (! $input || ! $output) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                gzclose($output);
            }
            throw new \RuntimeException('ไม่สามารถสร้างไฟล์บีบอัดได้');
        }
        try {
            while (! feof($input)) {
                $bytes = fread($input, 1024 * 1024);
                if ($bytes === false || gzwrite($output, $bytes) !== strlen($bytes)) {
                    throw new \RuntimeException('เขียนไฟล์บีบอัดไม่ครบ กรุณาตรวจพื้นที่ดิสก์');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    private function portableDump(string $destination): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $schema = (string) config('database.connections.mysql.database');
        foreach (['VIEWS' => 'TABLE_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $catalog => $column) {
            if ($connection->selectOne("SELECT COUNT(*) AS total FROM information_schema.{$catalog} WHERE {$column} = ?", [$schema])->total > 0) {
                throw new \RuntimeException('มี views/triggers/routines/events ที่ตัวสำรอง PHP ไม่รองรับ กรุณาติดตั้ง mysqldump ที่เข้ากันได้');
            }
        }
        $tables = collect($connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn ($row) => array_values((array) $row)[0]);
        $handle = fopen($destination, 'wb');
        if (! $handle) {
            throw new \RuntimeException('ไม่สามารถสร้างไฟล์ SQL ได้');
        }

        // InnoDB provides one consistent snapshot instead of reading each table
        // at a different point in time. Schema changes still require a quiet window.
        $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $connection->beginTransaction();
        try {
            $this->writeAll($handle, "-- MONSTOPIA MySQL backup\n-- Created: ".now()->toIso8601String()."\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            foreach ($tables as $table) {
                $quotedTable = $this->quoteIdentifier($table);
                $createRow = (array) $connection->selectOne("SHOW CREATE TABLE {$quotedTable}");
                $createSql = array_values($createRow)[1] ?? null;
                if (! $createSql) {
                    throw new \RuntimeException("อ่านโครงสร้างตาราง {$table} ไม่สำเร็จ");
                }
                $this->writeAll($handle, "DROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

                foreach ($connection->table($table)->cursor()->chunk(200) as $chunk) {
                    $first = (array) $chunk->first();
                    $columns = implode(', ', array_map(fn ($column) => $this->quoteIdentifier($column), array_keys($first)));
                    $values = $chunk->map(function ($row) use ($pdo) {
                        return '('.implode(', ', array_map(fn ($value) => $this->quoteValue($pdo, $value), array_values((array) $row))).')';
                    })->implode(",\n");
                    $this->writeAll($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES\n{$values};\n");
                }
                $this->writeAll($handle, "\n");
            }
            $this->writeAll($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            if (! fflush($handle)) {
                throw new \RuntimeException('บันทึกไฟล์ SQL ไม่ครบ กรุณาตรวจพื้นที่ดิสก์');
            }
            $connection->commit();
        } catch (Throwable $error) {
            $connection->rollBack();
            throw $error;
        } finally {
            fclose($handle);
        }
    }

    private function writeAll($handle, string $bytes): void
    {
        $length = strlen($bytes);
        $offset = 0;
        while ($offset < $length) {
            // Limit each allocation/write; retry only after positive progress.
            // A zero/false write fails immediately instead of looping forever.
            $written = @fwrite($handle, substr($bytes, $offset, min(1024 * 1024, $length - $offset)));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('เขียนไฟล์ SQL ไม่ครบ กรุณาตรวจพื้นที่ดิสก์');
            }
            $offset += $written;
        }
    }

    private function quoteIdentifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function quoteValue(\PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    private function deleteExpiredBackups(string $directory): void
    {
        $cutoff = now()->subDays(max(1, (int) config('monstopia.backup_retention_days')))->getTimestamp();
        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'monstopia_*.sql.gz') as $file) {
            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
            }
        }
    }
}

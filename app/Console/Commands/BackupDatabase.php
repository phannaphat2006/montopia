<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'monstopia:backup-database';

    protected $description = 'Create a compressed MySQL backup in private storage';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('คำสั่งสำรองข้อมูลนี้รองรับฐานข้อมูล MySQL เท่านั้น');

            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $backupDir = storage_path('app/private/backups');
        File::ensureDirectoryExists($backupDir);
        $stamp = now()->format('Y-m-d_H-i-s');
        $sqlPath = $backupDir.DIRECTORY_SEPARATOR."monstopia_{$stamp}.sql";
        $gzipPath = $sqlPath.'.gz';
        $credentialsPath = storage_path('framework'.DIRECTORY_SEPARATOR.'mysql-backup-'.bin2hex(random_bytes(8)).'.cnf');

        try {
            File::put($credentialsPath, $this->credentialsFile($connection));
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
            $this->deleteExpiredBackups($backupDir);
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
            throw new \RuntimeException('ไม่สามารถสร้างไฟล์บีบอัดได้');
        }
        while (! feof($input)) {
            gzwrite($output, (string) fread($input, 1024 * 1024));
        }
        fclose($input);
        gzclose($output);
    }

    private function portableDump(string $destination): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $tables = collect($connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn ($row) => array_values((array) $row)[0]);
        $handle = fopen($destination, 'wb');
        if (! $handle) {
            throw new \RuntimeException('ไม่สามารถสร้างไฟล์ SQL ได้');
        }

        fwrite($handle, "-- MONSTOPIA MySQL backup\n-- Created: ".now()->toIso8601String()."\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tables as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $createRow = (array) $connection->selectOne("SHOW CREATE TABLE {$quotedTable}");
            $createSql = array_values($createRow)[1] ?? null;
            if (! $createSql) {
                throw new \RuntimeException("อ่านโครงสร้างตาราง {$table} ไม่สำเร็จ");
            }
            fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

            $rows = $connection->table($table)->get();
            foreach ($rows->chunk(200) as $chunk) {
                $first = (array) $chunk->first();
                $columns = implode(', ', array_map(fn ($column) => $this->quoteIdentifier($column), array_keys($first)));
                $values = $chunk->map(function ($row) use ($pdo) {
                    return '('.implode(', ', array_map(fn ($value) => $this->quoteValue($pdo, $value), array_values((array) $row))).')';
                })->implode(",\n");
                fwrite($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES\n{$values};\n");
            }
            fwrite($handle, "\n");
        }
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
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

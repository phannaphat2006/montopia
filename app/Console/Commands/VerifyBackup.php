<?php

namespace App\Console\Commands;

use App\Services\BackupBundleService;
use Illuminate\Console\Command;
use Throwable;

class VerifyBackup extends Command
{
    protected $signature = 'monstopia:verify-backup {bundle : Absolute path to a complete trusted backup bundle} {--files-only : Verify and restore files without importing a database}';

    protected $description = 'Verify checksums and restore into fresh private test storage and a separate test MySQL server only';

    public function handle(BackupBundleService $backups): int
    {
        try {
            $result = $backups->verify($this->argument('bundle'), (bool) $this->option('files-only'));
            $this->info(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('ตรวจหรือกู้คืนชุดสำรองไม่สำเร็จ: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\BackupBundleService;
use Illuminate\Console\Command;
use Throwable;

class BackupAll extends Command
{
    protected $signature = 'monstopia:backup-all';

    protected $description = 'Back up MySQL and every referenced project attachment with checksum manifest';

    public function handle(BackupBundleService $backups): int
    {
        try {
            $this->info('สำรอง SQL + ไฟล์แนบสำเร็จ: '.$backups->create());

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('สำรองทั้งหมดไม่สำเร็จ: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}

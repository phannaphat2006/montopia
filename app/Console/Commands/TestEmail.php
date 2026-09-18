<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class TestEmail extends Command
{
    protected $signature = 'monstopia:test-email {recipient : Email address that you control}';

    protected $description = 'Send one harmless SMTP test message to a specified recipient';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('กรุณาระบุอีเมลผู้รับให้ถูกต้อง');

            return self::FAILURE;
        }
        if (config('mail.default') !== 'smtp') {
            $this->error('ยังไม่ได้ใช้ SMTP: คำสั่งนี้ไม่ถือว่า array/log คือการส่งอีเมลจริง');

            return self::FAILURE;
        }

        $reference = 'MONSTOPIA-SMTP-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        config(['mail.mailers.smtp.timeout' => 20]);
        try {
            Mail::raw("ข้อความนี้ใช้ทดสอบการส่งอีเมลจาก MONSTOPIA เท่านั้น ไม่มีข้อมูลลูกค้า\nหมายเลขทดสอบ: {$reference}", function ($message) use ($recipient, $reference) {
                $message->to($recipient)->subject($reference);
            });
        } catch (Throwable $error) {
            $this->error('ส่งไม่สำเร็จ กรุณาตรวจ SMTP/เครือข่าย รหัสข้อผิดพลาด: '.class_basename($error));
            $this->line('ไม่แสดงข้อมูลรับรอง และไม่ลองส่งซ้ำอัตโนมัติ');

            return self::FAILURE;
        }
        $this->info('SMTP รับข้อความแล้ว: '.$reference);
        $this->line('ยังต้องตรวจกล่องจดหมายและ Spam ของผู้รับ จึงยืนยันว่าถึงจริง');

        return self::SUCCESS;
    }
}

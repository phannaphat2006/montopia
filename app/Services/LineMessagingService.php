<?php

namespace App\Services;

use App\Models\Inquiry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LineMessagingService
{
    public function notifyNewInquiry(Inquiry $inquiry): bool
    {
        $token = config('services.line.channel_access_token');
        $target = config('services.line.target_id');
        if (! $token || ! $target) {
            return false;
        }
        try {
            $response = Http::withToken($token)->withHeaders(['X-Line-Retry-Key' => (string) Str::uuid()])->timeout(5)->post('https://api.line.me/v2/bot/message/push', ['to' => $target, 'messages' => [['type' => 'text', 'text' => "มีบรีฟโครงการใหม่ #{$inquiry->id}\n{$inquiry->client_name}\nงบประมาณ: {$inquiry->budget_range}"]]]);
            $response->throw();

            return true;
        } catch (Throwable $error) {
            Log::warning('LINE Messaging API notification failed', ['inquiry_id' => $inquiry->id, 'exception' => $error::class]);

            return false;
        }
    }
}

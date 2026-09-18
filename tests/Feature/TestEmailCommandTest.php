<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class TestEmailCommandTest extends TestCase
{
    public function test_invalid_recipient_is_rejected_without_sending(): void
    {
        Mail::shouldReceive('raw')->never();
        $this->artisan('monstopia:test-email', ['recipient' => 'not-email'])->assertFailed();
    }

    public function test_simulated_transport_is_not_claimed_as_delivery(): void
    {
        config(['mail.default' => 'array']);
        Mail::shouldReceive('raw')->never();
        $this->artisan('monstopia:test-email', ['recipient' => 'owner@example.com'])->assertFailed();
    }

    public function test_smtp_test_sends_once_and_requires_inbox_confirmation(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $this->artisan('monstopia:test-email', ['recipient' => 'owner@example.com'])
            ->expectsOutputToContain('ยังต้องตรวจกล่องจดหมาย')->assertSuccessful();
    }

    public function test_transport_errors_do_not_expose_credentials_or_retry(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('secret-must-not-be-shown'));
        $this->artisan('monstopia:test-email', ['recipient' => 'owner@example.com'])
            ->expectsOutputToContain('RuntimeException')
            ->expectsOutputToContain('ไม่ลองส่งซ้ำ')
            ->doesntExpectOutputToContain('secret-must-not-be-shown')
            ->assertFailed();
    }
}

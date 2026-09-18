<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_updates', function (Blueprint $table) {
            // Old records may already have been mailed; never assume that they failed.
            $table->string('email_status', 20)->default('unknown')->index();
            $table->boolean('email_notification_enabled')->default(false);
            $table->unsignedTinyInteger('email_attempts')->default(0);
            $table->timestamp('email_last_attempt_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->string('email_error', 255)->nullable();
            $table->string('email_transport', 40)->nullable();
            $table->uuid('email_claim_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_updates', function (Blueprint $table) {
            $table->dropIndex(['email_status']);
            $table->dropColumn([
                'email_status', 'email_notification_enabled', 'email_attempts',
                'email_last_attempt_at', 'email_sent_at', 'email_error',
                'email_transport', 'email_claim_token',
            ]);
        });
    }
};

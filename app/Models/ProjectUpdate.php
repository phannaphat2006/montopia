<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectUpdate extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'type',
        'title',
        'body',
        'from_status',
        'to_status',
        'progress_percent',
        'visible_to_client',
        'email_status',
        'email_notification_enabled',
        'email_attempts',
        'email_last_attempt_at',
        'email_sent_at',
        'email_error',
        'email_transport',
        'email_claim_token',
    ];

    // Client responses must not expose transport state or delivery metadata.
    protected $hidden = [
        'email_status', 'email_notification_enabled', 'email_attempts',
        'email_last_attempt_at', 'email_sent_at', 'email_error',
        'email_transport', 'email_claim_token',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'visible_to_client' => 'boolean',
            'email_notification_enabled' => 'boolean',
            'email_attempts' => 'integer',
            'email_last_attempt_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function exposeEmailDelivery(): self
    {
        return $this->makeVisible([
            'email_status', 'email_attempts', 'email_last_attempt_at',
            'email_sent_at', 'email_error', 'email_transport',
        ])->append('email_can_retry');
    }

    public function getEmailCanRetryAttribute(): bool
    {
        return $this->email_notification_enabled && $this->visible_to_client
            && $this->email_attempts < 3
            && in_array($this->email_status, ['pending', 'failed', 'skipped', 'simulated'], true);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

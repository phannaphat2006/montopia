<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProjectAttachment extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'visibility',
    ];

    protected $hidden = ['stored_path'];

    protected $appends = ['download_url'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (ProjectAttachment $attachment) {
            Storage::disk('local')->delete($attachment->getRawOriginal('stored_path'));
        });
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('project-files.download', $this);
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

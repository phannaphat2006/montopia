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
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'visible_to_client' => 'boolean',
        ];
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['inquiry_id', 'client_user_id', 'project_name', 'client_name', 'total_budget', 'status', 'progress_percent', 'start_date', 'end_date', 'updated_by'];

    protected $hidden = ['total_budget'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'total_budget' => 'decimal:2', 'progress_percent' => 'integer'];
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class)->orderBy('due_date');
    }

    public function updates()
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function attachments()
    {
        return $this->hasMany(ProjectAttachment::class)->latest();
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

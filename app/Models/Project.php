<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['inquiry_id', 'client_user_id', 'project_name', 'client_name', 'total_budget', 'status', 'start_date', 'end_date'];

    protected $hidden = ['total_budget'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'total_budget' => 'decimal:2'];
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
}

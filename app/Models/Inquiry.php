<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $fillable = ['client_name', 'client_email', 'client_phone', 'budget_range', 'project_scope', 'status'];

    public function replies()
    {
        return $this->hasMany(InquiryReply::class);
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }
}

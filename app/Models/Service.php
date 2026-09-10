<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'description',
        'icon_label',
        'display_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'display_order' => 'integer'];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }
}

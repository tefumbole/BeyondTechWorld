<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventPackage extends Model
{
    protected $fillable = [
        'category', 'code', 'name', 'description', 'base_price', 'currency',
        'duration_days', 'active', 'sort_order', 'icon', 'metadata',
    ];

    protected $casts = [
        'base_price' => 'float',
        'active' => 'boolean',
        'duration_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function components()
    {
        return $this->hasMany(EventPackageComponent::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category', strtoupper((string) $category));
    }
}

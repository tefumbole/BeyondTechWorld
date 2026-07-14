<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventWorkerCategory extends Model
{
    protected $fillable = [
        'name', 'code', 'description',
        'default_daily_rate', 'default_hourly_rate', 'overtime_hourly_rate',
        'minimum_payable_hours', 'budget_weight', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_daily_rate' => 'integer',
        'default_hourly_rate' => 'integer',
        'overtime_hourly_rate' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

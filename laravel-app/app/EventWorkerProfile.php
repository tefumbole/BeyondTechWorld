<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventWorkerProfile extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_id', 'user_id', 'be_user_id', 'worker_category_id',
        'standard_daily_rate', 'standard_hourly_rate', 'overtime_rate',
        'skills', 'specialization', 'experience_level',
        'telephone', 'email', 'address',
        'mobile_money_details', 'bank_details', 'emergency_contact',
        'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'standard_daily_rate' => 'integer',
        'standard_hourly_rate' => 'integer',
        'overtime_rate' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(EventWorkerCategory::class, 'worker_category_id');
    }

    public function assignments()
    {
        return $this->hasMany(EventAssignment::class, 'worker_profile_id');
    }

    public function displayName()
    {
        if ($this->customer) {
            return $this->customer->name;
        }
        if ($this->user) {
            return $this->user->name;
        }

        return $this->telephone ?: $this->email ?: 'Worker';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

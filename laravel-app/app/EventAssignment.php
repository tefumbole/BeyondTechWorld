<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventAssignment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'worker_profile_id', 'assignment_role', 'is_supervisor',
        'reporting_time', 'work_start_date', 'work_end_date', 'expected_days',
        'default_daily_rate', 'event_daily_rate', 'hourly_rate', 'fixed_amount',
        'compensation_method', 'expected_total', 'food_per_day',
        'contract_status', 'attendance_status', 'timesheet_status', 'payment_status',
        'notes', 'invite_token', 'acceptance_status', 'accepted_at', 'invited_at',
    ];

    protected $dates = ['reporting_time', 'work_start_date', 'work_end_date', 'accepted_at', 'invited_at'];

    protected $casts = [
        'is_supervisor' => 'boolean',
        'expected_days' => 'integer',
        'expected_total' => 'integer',
        'food_per_day' => 'integer',
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

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function workerProfile()
    {
        return $this->belongsTo(EventWorkerProfile::class, 'worker_profile_id');
    }

    public function contract()
    {
        return $this->hasOne(EventContract::class, 'assignment_id');
    }

    public function timesheets()
    {
        return $this->hasMany(EventTimesheet::class, 'assignment_id');
    }

    public function payments()
    {
        return $this->hasMany(EventWorkerPayment::class, 'assignment_id');
    }
}

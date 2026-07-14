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
        'compensation_method', 'expected_total',
        'contract_status', 'attendance_status', 'timesheet_status', 'payment_status',
        'notes',
    ];

    protected $dates = ['reporting_time', 'work_start_date', 'work_end_date'];

    protected $casts = [
        'is_supervisor' => 'boolean',
        'expected_days' => 'integer',
        'expected_total' => 'integer',
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

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventTimesheet extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'event_id', 'assignment_id', 'worker_profile_id', 'status',
        'period_start', 'period_end', 'total_days', 'total_hours', 'notes',
        'submitted_at', 'approved_at', 'approved_by', 'rejection_reason',
    ];

    protected $dates = ['period_start', 'period_end', 'submitted_at', 'approved_at'];

    protected $casts = [
        'total_days' => 'integer',
        'total_hours' => 'float',
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

    public function assignment()
    {
        return $this->belongsTo(EventAssignment::class, 'assignment_id');
    }

    public function workerProfile()
    {
        return $this->belongsTo(EventWorkerProfile::class, 'worker_profile_id');
    }

    public function entries()
    {
        return $this->hasMany(EventTimesheetEntry::class, 'timesheet_id')->orderBy('work_date');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusLabel()
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ][$this->status] ?? $this->status;
    }
}

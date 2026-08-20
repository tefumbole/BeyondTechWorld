<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TimesheetEntry extends Model
{
    protected $table = 'be_timesheet_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'be_user_id', 'user_id', 'employee_name', 'activity_id', 'activity_name',
        'assignment_id', 'entry_date', 'hours', 'notes', 'status',
        'approved_by', 'approved_at', 'review_note',
    ];

    protected $dates = ['entry_date', 'approved_at'];

    protected $casts = [
        'hours' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function activity()
    {
        return $this->belongsTo(TimesheetActivity::class, 'activity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignment()
    {
        return $this->belongsTo(InternshipTaskAssignment::class, 'assignment_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventTimesheetEntry extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['timesheet_id', 'work_date', 'hours', 'notes'];

    protected $dates = ['work_date'];

    protected $casts = ['hours' => 'float'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function timesheet()
    {
        return $this->belongsTo(EventTimesheet::class, 'timesheet_id');
    }
}

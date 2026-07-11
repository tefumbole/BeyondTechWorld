<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimesheetEntry extends Model
{
    protected $table = 'be_timesheet_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'be_user_id', 'activity_id', 'activity_name',
        'entry_date', 'hours', 'notes', 'status',
    ];

    protected $dates = ['entry_date'];

    protected $casts = [
        'hours' => 'float',
    ];

    public function activity()
    {
        return $this->belongsTo(TimesheetActivity::class, 'activity_id');
    }
}

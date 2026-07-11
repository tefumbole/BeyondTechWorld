<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimesheetActivity extends Model
{
    protected $table = 'be_timesheet_activities';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'color', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

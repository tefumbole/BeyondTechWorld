<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TimesheetCategory extends Model
{
    protected $table = 'be_timesheet_categories';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
        });
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TimesheetActivity extends Model
{
    protected $table = 'be_timesheet_activities';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'name', 'description', 'category_id', 'category', 'color',
        'is_active', 'owner_user_id', 'owner_be_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function categoryRel()
    {
        return $this->belongsTo(TimesheetCategory::class, 'category_id');
    }
}

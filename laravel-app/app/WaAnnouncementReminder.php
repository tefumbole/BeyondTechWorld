<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WaAnnouncementReminder extends Model
{
    protected $table = 'wa_announcement_reminders';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'reminder_time' => 'datetime',
        'is_sent' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function announcement()
    {
        return $this->belongsTo(WaAnnouncement::class, 'announcement_id');
    }
}

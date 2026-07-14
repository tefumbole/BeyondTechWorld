<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventReminder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'remind_at', 'message', 'channel',
        'recipient_type', 'recipient_phone', 'sent_at', 'send_error', 'created_by',
    ];

    protected $dates = ['remind_at', 'sent_at'];

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

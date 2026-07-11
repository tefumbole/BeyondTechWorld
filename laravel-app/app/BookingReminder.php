<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BookingReminder extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'remind_at',
        'sent_at',
        'message',
    ];

    protected $dates = [
        'remind_at',
        'sent_at',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

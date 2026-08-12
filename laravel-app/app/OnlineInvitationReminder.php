<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnlineInvitationReminder extends Model
{
    protected $guarded = [];

    protected $dates = ['remind_at', 'sent_at'];

    public function event()
    {
        return $this->belongsTo(OnlineInvitationEvent::class, 'event_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

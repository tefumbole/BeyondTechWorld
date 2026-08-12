<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnlineInvitationRequestLink extends Model
{
    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(OnlineInvitationEvent::class, 'event_id');
    }

    public function category()
    {
        return $this->belongsTo(OnlineInvitationCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

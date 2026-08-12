<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnlineInvitationCategory extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function events()
    {
        return $this->belongsToMany(
            OnlineInvitationEvent::class,
            'online_invitation_event_category',
            'category_id',
            'event_id'
        );
    }
}


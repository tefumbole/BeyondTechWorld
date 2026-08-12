<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnlineInvitationEvent extends Model
{
    protected $guarded = [];

    protected $dates = ['event_at', 'created_at', 'updated_at'];

    public function template()
    {
        return $this->belongsTo(OnlineInvitationTemplate::class, 'template_id', 'id');
    }

    public function categories()
    {
        return $this->belongsToMany(
            OnlineInvitationCategory::class,
            'online_invitation_event_category',
            'event_id',
            'category_id'
        );
    }

    public function invitations()
    {
        return $this->hasMany(OnlineInvitation::class, 'event_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}


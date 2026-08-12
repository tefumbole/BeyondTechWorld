<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnlineInvitationTemplate extends Model
{
    protected $guarded = [];

    public function events()
    {
        return $this->hasMany(OnlineInvitationEvent::class, 'template_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}


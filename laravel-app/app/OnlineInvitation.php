<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OnlineInvitation extends Model
{
    protected $guarded = [];

    protected $dates = ['sent_at', 'rsvp_at', 'accepted_at', 'used_at', 'created_at', 'updated_at'];

    public function event()
    {
        return $this->belongsTo(OnlineInvitationEvent::class, 'event_id', 'id');
    }

    public function isAttending(): bool
    {
        return ($this->rsvp_status ?? '') === 'accepted';
    }

    public function isAdmitted(): bool
    {
        return ! empty($this->used_at);
    }

    public function category()
    {
        return $this->belongsTo(OnlineInvitationCategory::class, 'category_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}

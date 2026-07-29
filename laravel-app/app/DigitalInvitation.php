<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DigitalInvitation extends Model
{
    protected $connection = 'beyond_data';

    protected $table = 'invitations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_id',
        'guest_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'qr_code',
        'status',
        'checked_in',
        'checked_in_at',
        'invitation_type',
        'template_id',
        'qr_code_url',
        'image_url',
        'sent_at',
        'failed_at',
        'failure_reason',
        'generated_at',
    ];

    protected $casts = [
        'checked_in' => 'boolean',
    ];

    public function guest()
    {
        return $this->belongsTo(InvitationGuest::class, 'guest_id', 'id');
    }

    public function event()
    {
        return $this->belongsTo(InvitationEvent::class, 'event_id', 'id');
    }

    public function displayName()
    {
        if ($this->guest_name) {
            return $this->guest_name;
        }

        return optional($this->guest)->name ?: 'Unknown';
    }

    public function displayPhone()
    {
        if ($this->guest_phone) {
            return $this->guest_phone;
        }

        return optional($this->guest)->phone;
    }
}

<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppVerificationSession extends Model
{
    protected $table = 'whatsapp_verification_sessions';

    protected $fillable = [
        'whatsapp_contact_id', 'conversation_id', 'challenge_id', 'identity_type', 'identity_id',
        'purpose', 'method', 'verified_at', 'expires_at', 'invalidated_at',
    ];

    protected $dates = ['verified_at', 'expires_at', 'invalidated_at'];

    public function isActive()
    {
        return $this->invalidated_at === null && $this->expires_at && $this->expires_at->gt(now());
    }
}

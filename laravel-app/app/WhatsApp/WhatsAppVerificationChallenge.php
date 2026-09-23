<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppVerificationChallenge extends Model
{
    protected $table = 'whatsapp_verification_challenges';

    protected $hidden = ['otp_hash'];

    protected $fillable = [
        'whatsapp_contact_id', 'conversation_id', 'identity_type', 'identity_id', 'purpose',
        'otp_hash', 'expires_at', 'attempts', 'max_attempts', 'verified_at', 'invalidated_at',
        'provider_message_id',
    ];

    protected $dates = ['expires_at', 'verified_at', 'invalidated_at'];
}

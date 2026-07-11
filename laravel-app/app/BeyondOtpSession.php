<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BeyondOtpSession extends Model
{
    protected $table = 'be_otp_sessions';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'phone', 'otp', 'expires_at', 'attempts', 'resend_count',
        'verified_at', 'purpose', 'created_at',
    ];

    protected $dates = ['expires_at', 'verified_at', 'created_at'];
}

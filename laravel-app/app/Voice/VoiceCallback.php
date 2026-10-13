<?php

namespace App\Voice;

use Illuminate\Database\Eloquent\Model;

class VoiceCallback extends Model
{
    protected $table = 'voice_callbacks';

    protected $fillable = [
        'cloud_tenant_id', 'voice_call_id', 'caller_phone', 'caller_name',
        'requested_for', 'reason', 'message', 'requested_at', 'status',
    ];

    protected $dates = ['requested_at'];
}

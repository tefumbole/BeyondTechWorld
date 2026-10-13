<?php

namespace App\Voice;

use Illuminate\Database\Eloquent\Model;

class VoiceCall extends Model
{
    protected $table = 'voice_calls';

    protected $fillable = [
        'cloud_tenant_id', 'voice_connection_id', 'conversation_id',
        'provider', 'provider_call_id', 'caller_phone', 'called_number',
        'status', 'status_reason', 'handling', 'transfer_status',
        'recording', 'identity_verified', 'caller_name',
        'duration_seconds', 'last_latency_ms', 'silent_turns',
        'state', 'transcript', 'started_at', 'answered_at', 'ended_at',
    ];

    protected $casts = [
        'recording' => 'boolean',
        'identity_verified' => 'boolean',
    ];

    protected $dates = ['started_at', 'answered_at', 'ended_at'];

    public function stateArray()
    {
        $decoded = json_decode((string) $this->state, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function transcriptArray()
    {
        $decoded = json_decode((string) $this->transcript, true);

        return is_array($decoded) ? $decoded : [];
    }
}

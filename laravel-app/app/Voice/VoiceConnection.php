<?php

namespace App\Voice;

use Illuminate\Database\Eloquent\Model;

class VoiceConnection extends Model
{
    protected $table = 'voice_connections';

    protected $fillable = [
        'cloud_tenant_id', 'tenant_type', 'called_number', 'provider', 'status',
        'ai_enabled', 'company_name', 'assistant_name', 'language', 'greeting',
        'transfer_number', 'business_hours',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
    ];

    public function hours()
    {
        $raw = $this->business_hours;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}

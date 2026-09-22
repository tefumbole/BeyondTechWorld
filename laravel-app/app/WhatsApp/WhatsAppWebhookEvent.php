<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    const RECEIVED = 'RECEIVED';
    const QUEUED = 'QUEUED';
    const PROCESSING = 'PROCESSING';
    const PROCESSED = 'PROCESSED';
    const FAILED = 'FAILED';
    const IGNORED = 'IGNORED';

    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'provider', 'provider_event_id', 'fingerprint', 'event_type', 'payload',
        'signature_verified', 'status', 'attempts', 'processing_error',
        'received_at', 'processing_started_at', 'processed_at',
    ];

    protected $dates = ['received_at', 'processing_started_at', 'processed_at'];

    protected $casts = [
        'signature_verified' => 'boolean',
    ];

    public function payloadArray()
    {
        $raw = $this->payload;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

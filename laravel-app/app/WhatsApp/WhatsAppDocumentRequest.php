<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppDocumentRequest extends Model
{
    protected $table = 'whatsapp_document_requests';

    const REQUESTED = 'REQUESTED';
    const AWAITING_OTP = 'AWAITING_OTP';
    const VERIFIED = 'VERIFIED';
    const AUTHORIZED = 'AUTHORIZED';
    const GENERATING = 'GENERATING';
    const SENDING = 'SENDING';
    const SENT = 'SENT';
    const FAILED = 'FAILED';
    const DENIED = 'DENIED';
    const EXPIRED = 'EXPIRED';

    protected $fillable = [
        'whatsapp_contact_id', 'conversation_id', 'identity_type', 'identity_id', 'document_type',
        'requested_reference', 'resolved_record_id', 'sensitivity', 'challenge_id', 'session_id',
        'status', 'failure_code', 'public_message', 'file_name', 'local_path', 'ephemeral',
        'provider_message_id', 'outbound_message_id', 'requested_at', 'authorized_at', 'sent_at', 'failed_at',
    ];

    protected $dates = ['requested_at', 'authorized_at', 'sent_at', 'failed_at'];

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }
}

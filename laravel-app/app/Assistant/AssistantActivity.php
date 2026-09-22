<?php

namespace App\Assistant;

use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;

class AssistantActivity extends Model
{
    const STARTED = 'started';
    const COMPLETED = 'completed';
    const SKIPPED = 'skipped';
    const FAILED = 'failed';
    const HANDED_OVER = 'handed_over';

    protected $table = 'assistant_activities';

    protected $fillable = [
        'conversation_id', 'message_id', 'incoming_fingerprint', 'intent', 'confidence',
        'action', 'tools_requested', 'tools_executed', 'tool_status', 'response_preview',
        'sent', 'provider', 'model', 'input_tokens', 'output_tokens', 'duration_ms',
        'handover_reason', 'status', 'error',
    ];

    protected $casts = ['sent' => 'boolean', 'confidence' => 'float'];

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function message()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'message_id');
    }
}

<?php

namespace App\Assistant;

use App\WhatsApp\WhatsAppConversation;
use Illuminate\Database\Eloquent\Model;

class AssistantMemory extends Model
{
    protected $table = 'assistant_memories';

    protected $fillable = [
        'conversation_id', 'active_intent', 'parameters_json', 'missing_json',
        'last_tool', 'last_tool_result', 'clarification_count', 'suggested_reply', 'expires_at',
    ];

    protected $dates = ['expires_at'];

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function parameters()
    {
        $decoded = json_decode((string) $this->parameters_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function missing()
    {
        $decoded = json_decode((string) $this->missing_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setParameters(array $params)
    {
        $this->parameters_json = json_encode($params);
    }

    public function setMissing(array $missing)
    {
        $this->missing_json = json_encode(array_values($missing));
    }
}

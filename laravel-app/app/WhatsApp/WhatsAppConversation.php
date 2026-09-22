<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    const MODE_AI = 'AI';
    const MODE_HUMAN = 'HUMAN';
    const MODE_PAUSED = 'PAUSED';
    const MODE_CLOSED = 'CLOSED';

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'contact_id', 'assigned_user_id', 'mode', 'status', 'unread_count',
        'first_message', 'last_message', 'last_activity_at',
        'last_incoming_at', 'last_outgoing_at',
    ];

    protected $dates = ['last_activity_at', 'last_incoming_at', 'last_outgoing_at'];

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

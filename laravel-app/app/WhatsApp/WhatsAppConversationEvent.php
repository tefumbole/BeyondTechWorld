<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversationEvent extends Model
{
    const CONTACT_CREATED = 'contact_created';
    const LEAD_CREATED = 'lead_created';
    const ASSIGNED = 'staff_assigned';
    const REPLIED = 'staff_replied';
    const STATUS = 'status_changed';
    const MODE = 'mode_changed';
    const NOTE = 'note_added';
    const DOCUMENT = 'document_sent';
    const QUOTATION = 'quotation_linked';
    const CLOSED = 'conversation_closed';
    const REOPENED = 'conversation_reopened';
    const TAKEOVER = 'takeover';
    const RELEASED = 'released';
    const CONVERTED = 'lead_converted';

    protected $table = 'whatsapp_conversation_events';

    protected $fillable = [
        'conversation_id', 'type', 'body', 'actor_user_id', 'meta',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

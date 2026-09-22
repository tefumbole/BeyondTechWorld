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

    const STATUS_OPEN = 'OPEN';
    const STATUS_WAITING_CUSTOMER = 'WAITING_CUSTOMER';
    const STATUS_WAITING_STAFF = 'WAITING_STAFF';
    const STATUS_RESOLVED = 'RESOLVED';
    const STATUS_CLOSED = 'CLOSED';

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

    public function events()
    {
        return $this->hasMany(WhatsAppConversationEvent::class, 'conversation_id')->orderBy('id');
    }

    public function notes()
    {
        return $this->hasMany(WhatsAppNote::class, 'conversation_id')->orderBy('id');
    }

    public function lead()
    {
        return $this->hasOne(Lead::class, 'conversation_id')->whereNotIn('status', LeadCatalog::closedStatuses());
    }

    public function isAwaitingStaff()
    {
        if ($this->last_incoming_at && $this->last_outgoing_at) {
            return $this->last_incoming_at->gt($this->last_outgoing_at);
        }

        return $this->last_incoming_at && ! $this->last_outgoing_at;
    }

    public function waitingMinutes()
    {
        if (! $this->isAwaitingStaff() || ! $this->last_incoming_at) {
            return 0;
        }

        return max(0, $this->last_incoming_at->diffInMinutes(now()));
    }
}

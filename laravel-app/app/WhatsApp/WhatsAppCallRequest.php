<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppCallRequest extends Model
{
    const REQUESTED = 'REQUESTED';
    const ASSIGNED = 'ASSIGNED';
    const CONTACTED = 'CONTACTED';
    const COMPLETED = 'COMPLETED';
    const CANCELLED = 'CANCELLED';

    protected $table = 'whatsapp_call_requests';

    protected $fillable = [
        'conversation_id', 'contact_id', 'status', 'assigned_user_id',
        'requested_body', 'notes', 'contacted_at', 'completed_at',
    ];

    protected $dates = ['contacted_at', 'completed_at'];

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

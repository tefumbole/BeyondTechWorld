<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppCall extends Model
{
    const RECEIVED = 'RECEIVED';
    const MISSED = 'MISSED';
    const FOLLOW_UP = 'FOLLOW_UP_REQUIRED';
    const CONTACTED = 'CONTACTED';
    const RESOLVED = 'RESOLVED';

    protected $table = 'whatsapp_calls';

    protected $fillable = [
        'provider_call_id', 'contact_id', 'caller_phone', 'call_type',
        'status', 'assigned_user_id', 'notes', 'called_at',
    ];

    protected $dates = ['called_at'];

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

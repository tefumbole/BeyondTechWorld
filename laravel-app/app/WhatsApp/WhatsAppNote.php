<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppNote extends Model
{
    protected $table = 'whatsapp_notes';

    protected $fillable = [
        'conversation_id', 'lead_id', 'author_id', 'body',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppGroupMessage extends Model
{
    protected $table = 'whatsapp_group_messages';

    protected $fillable = [
        'group_id', 'provider_message_id', 'participant_phone', 'participant_jid',
        'participant_name', 'body', 'derived_text', 'derived_label', 'reply_to_message_id',
        'media_json', 'message_at',
    ];

    protected $dates = ['message_at'];

    public function group()
    {
        return $this->belongsTo(WhatsAppGroup::class, 'group_id');
    }
}

<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppGroupParticipant extends Model
{
    protected $table = 'whatsapp_group_participants';

    protected $fillable = ['group_id', 'phone', 'jid', 'display_name', 'user_id'];
}

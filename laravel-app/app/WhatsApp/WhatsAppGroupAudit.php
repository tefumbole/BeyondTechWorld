<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppGroupAudit extends Model
{
    protected $table = 'whatsapp_group_audits';

    protected $fillable = ['group_id', 'actor_user_id', 'type', 'body'];
}

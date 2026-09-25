<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppGroupAction extends Model
{
    const SUGGESTED = 'SUGGESTED';
    const CONFIRMED = 'CONFIRMED';

    protected $table = 'whatsapp_group_actions';

    protected $fillable = [
        'group_id', 'message_id', 'description', 'responsible_name', 'due_text',
        'kind', 'status', 'confidence', 'task_id',
    ];
}

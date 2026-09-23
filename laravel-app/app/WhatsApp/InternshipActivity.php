<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class InternshipActivity extends Model
{
    protected $table = 'whatsapp_internship_activities';

    protected $fillable = [
        'conversation_id', 'intake_id', 'actor_user_id', 'type', 'body',
    ];
}

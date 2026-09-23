<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class AttendanceActivity extends Model
{
    protected $table = 'whatsapp_attendance_activities';

    protected $fillable = [
        'conversation_id', 'actor_user_id', 'employee_id', 'attendance_id', 'type', 'body',
    ];
}

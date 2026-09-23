<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable =[
        "date", "employee_id", "user_id", "intern_user_id",
        "checkin", "checkout", "status", "note",
        "source", "whatsapp_conversation_id", "whatsapp_message_id",
        "latitude", "longitude", "location_accuracy", "location_at", "location_status",
        "distance_meters", "allowed_radius_meters", "event_assignment_id"
    ];
}

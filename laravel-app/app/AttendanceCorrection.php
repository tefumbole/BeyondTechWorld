<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    protected $table = 'attendance_correction_requests';

    const PENDING = 'PENDING';
    const APPROVED = 'APPROVED';
    const REJECTED = 'REJECTED';

    protected $fillable = [
        'employee_id', 'user_id', 'intern_user_id', 'attendance_id', 'reason',
        'requested_checkout', 'status', 'approver_user_id', 'reviewed_at',
        'conversation_id', 'provider_message_id',
    ];

    protected $dates = ['reviewed_at'];
}

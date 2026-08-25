<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipSupervisorMessage extends Model
{
    protected $table = 'internship_supervisor_messages';

    protected $fillable = [
        'enrolment_id', 'student_user_id', 'supervisor_name', 'supervisor_phone',
        'body', 'reply_token', 'reply_body', 'replied_at',
    ];

    protected $dates = ['replied_at'];

    public function enrolment()
    {
        return $this->belongsTo(InternshipEnrolment::class, 'enrolment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function isReplied()
    {
        return ! empty($this->reply_body);
    }
}

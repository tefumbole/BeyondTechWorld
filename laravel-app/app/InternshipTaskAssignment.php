<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipTaskAssignment extends Model
{
    protected $table = 'internship_task_assignments';

    protected $fillable = [
        'enrolment_id', 'program_task_id', 'progression_day', 'scheduled_work_date',
        'released_at', 'status', 'attempt_count', 'whatsapp_sent_at', 'whatsapp_message_id',
    ];

    protected $dates = ['scheduled_work_date', 'released_at', 'whatsapp_sent_at'];

    protected $casts = [
        'progression_day' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function enrolment()
    {
        return $this->belongsTo(InternshipEnrolment::class, 'enrolment_id');
    }

    public function task()
    {
        return $this->belongsTo(InternshipProgramTask::class, 'program_task_id');
    }

    public function submissions()
    {
        return $this->hasMany(InternshipSubmission::class, 'assignment_id')->orderByDesc('attempt_no');
    }

    public function latestSubmission()
    {
        return $this->hasOne(InternshipSubmission::class, 'assignment_id')->latest('id');
    }
}

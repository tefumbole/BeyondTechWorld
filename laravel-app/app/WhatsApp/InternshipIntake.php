<?php

namespace App\WhatsApp;

use App\InternshipEnrolment;
use App\InternshipSubmission;
use App\InternshipTaskAssignment;
use App\User;
use Illuminate\Database\Eloquent\Model;

class InternshipIntake extends Model
{
    protected $table = 'whatsapp_internship_intakes';

    const COLLECTING = 'collecting';
    const AWAITING = 'awaiting_confirm';
    const SUBMITTED = 'submitted';
    const FAILED = 'failed';
    const EXPIRED = 'expired';

    protected $fillable = [
        'conversation_id', 'intern_user_id', 'enrolment_id', 'assignment_id', 'status',
        'text_body', 'links_json', 'validation_state', 'submission_id', 'provider_message_ids',
        'started_at', 'expires_at', 'confirmed_at', 'error',
    ];

    protected $dates = ['started_at', 'expires_at', 'confirmed_at'];

    public function files()
    {
        return $this->hasMany(InternshipIntakeFile::class, 'intake_id');
    }

    public function enrolment()
    {
        return $this->belongsTo(InternshipEnrolment::class, 'enrolment_id');
    }

    public function assignment()
    {
        return $this->belongsTo(InternshipTaskAssignment::class, 'assignment_id');
    }

    public function submission()
    {
        return $this->belongsTo(InternshipSubmission::class, 'submission_id');
    }

    public function intern()
    {
        return $this->belongsTo(User::class, 'intern_user_id');
    }

    public function links()
    {
        $decoded = json_decode((string) $this->links_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'applications';
    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_AWAITING = 'awaiting_approval';
    const STATUS_SELECTED = 'selected';
    const STATUS_REJECTED = 'rejected';
    const STATUS_HIRED = 'hired';

    protected $fillable = [
        'id', 'job_id', 'user_id', 'full_name', 'email', 'phone', 'whatsapp_number', 'country',
        'cover_letter', 'expected_salary', 'availability', 'availability_days',
        'cv_url', 'cv_path', 'student_id_path', 'internship_letter_path', 'selfie_path',
        'signature_image', 'agreement_token', 'agreement_sent_at', 'agreement_signed_at',
        'agreement_signature_image', 'status', 'reference_number', 'rejection_reason',
        'interview_date', 'submitted_at',
    ];

    protected $dates = ['interview_date', 'submitted_at', 'agreement_sent_at', 'agreement_signed_at'];

    public function job()
    {
        return $this->belongsTo(JobPosting::class, 'job_id', 'id');
    }

    public function notificationPhone()
    {
        return $this->whatsapp_number ?: $this->phone;
    }

    public function statusLabel()
    {
        $map = [
            self::STATUS_AWAITING => 'Awaiting Approval',
            self::STATUS_SELECTED => 'Selected',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_HIRED => 'Hired',
            'new' => 'Awaiting Approval',
            'reviewed' => 'Awaiting Approval',
            'interview' => 'Awaiting Approval',
            'shortlisted' => 'Selected',
            'withdrawn' => 'Rejected',
        ];

        return $map[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}

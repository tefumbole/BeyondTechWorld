<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $table = 'applications';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'job_id', 'user_id', 'full_name', 'email', 'phone', 'country',
        'cover_letter', 'expected_salary', 'availability', 'availability_days',
        'cv_url', 'cv_path', 'status', 'reference_number', 'rejection_reason',
        'interview_date', 'submitted_at',
    ];

    protected $dates = ['interview_date', 'submitted_at'];

    public function job()
    {
        return $this->belongsTo(JobPosting::class, 'job_id', 'id');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    protected $table = 'job_postings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'location', 'type', 'employment_type', 'posting_type',
        'department', 'salary', 'min_requirements', 'requirements', 'qualifications',
        'responsibilities', 'deadline', 'max_positions', 'max_applicants',
        'expected_applicants', 'enable_countdown', 'current_applicants', 'status',
        'posted_at', 'expires_at',
    ];

    protected $dates = ['deadline', 'posted_at', 'expires_at'];

    protected $casts = [
        'enable_countdown' => 'boolean',
        'max_positions' => 'integer',
        'expected_applicants' => 'integer',
        'current_applicants' => 'integer',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'job_id', 'id');
    }

    public function getIsExpiredAttribute()
    {
        return $this->deadline && $this->deadline->isPast();
    }

    public function isInternship()
    {
        return ($this->posting_type ?: 'job') === 'internship';
    }

    public function typeLabel()
    {
        return $this->isInternship() ? 'Internship' : 'Job';
    }
}

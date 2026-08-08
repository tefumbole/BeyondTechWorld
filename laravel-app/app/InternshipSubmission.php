<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipSubmission extends Model
{
    protected $table = 'internship_submissions';

    protected $fillable = [
        'assignment_id', 'student_user_id', 'attempt_no', 'description',
        'pdf_path', 'submitted_at', 'status',
    ];

    protected $dates = ['submitted_at'];

    protected $casts = [
        'attempt_no' => 'integer',
    ];

    public function assignment()
    {
        return $this->belongsTo(InternshipTaskAssignment::class, 'assignment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function files()
    {
        return $this->hasMany(InternshipSubmissionFile::class, 'submission_id');
    }

    public function grades()
    {
        return $this->hasMany(InternshipGrade::class, 'submission_id')->orderByDesc('id');
    }

    public function latestGrade()
    {
        return $this->hasOne(InternshipGrade::class, 'submission_id')->latest('id');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StudentProgress extends Model
{
    protected $table = 'student_progress';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'registration_id', 'course_id', 'course_name',
        'progress_percentage', 'status', 'start_date', 'completion_date',
    ];

    protected $dates = ['start_date', 'completion_date'];

    protected $casts = [
        'progress_percentage' => 'decimal:2',
    ];
}

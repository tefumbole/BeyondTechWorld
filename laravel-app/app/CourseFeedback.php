<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CourseFeedback extends Model
{
    protected $table = 'course_feedback';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'registration_id', 'course_id', 'student_name',
        'rating', 'feedback_text', 'status',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
}

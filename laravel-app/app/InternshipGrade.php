<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipGrade extends Model
{
    protected $table = 'internship_grades';

    protected $fillable = [
        'submission_id', 'grader_id', 'score', 'rubric_scores_json',
        'feedback', 'decision', 'graded_at', 'auto_accepted',
    ];

    protected $dates = ['graded_at'];

    protected $casts = [
        'score' => 'integer',
        'auto_accepted' => 'boolean',
    ];

    public function submission()
    {
        return $this->belongsTo(InternshipSubmission::class, 'submission_id');
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'grader_id');
    }

    public function rubricScores()
    {
        $raw = $this->rubric_scores_json;
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

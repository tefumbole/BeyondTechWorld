<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipSubmissionFile extends Model
{
    protected $table = 'internship_submission_files';

    protected $fillable = [
        'submission_id', 'disk', 'path', 'original_name', 'mime', 'size', 'checksum',
    ];

    public function submission()
    {
        return $this->belongsTo(InternshipSubmission::class, 'submission_id');
    }
}

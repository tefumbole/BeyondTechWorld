<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipSubmissionFile extends Model
{
    protected $table = 'internship_submission_files';

    protected $fillable = [
        'submission_id', 'disk', 'path', 'original_name', 'caption', 'sort_order',
        'mime', 'size', 'checksum',
    ];

    public function isImage()
    {
        return strpos((string) $this->mime, 'image/') === 0;
    }

    public function submission()
    {
        return $this->belongsTo(InternshipSubmission::class, 'submission_id');
    }
}

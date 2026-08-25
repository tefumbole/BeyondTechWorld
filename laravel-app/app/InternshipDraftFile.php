<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipDraftFile extends Model
{
    protected $table = 'internship_draft_files';

    protected $fillable = [
        'assignment_id', 'student_user_id', 'slot_index',
        'disk', 'path', 'original_name', 'mime', 'size', 'checksum', 'caption',
    ];

    public function assignment()
    {
        return $this->belongsTo(InternshipTaskAssignment::class, 'assignment_id');
    }

    public function isImage()
    {
        return strpos((string) $this->mime, 'image/') === 0;
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipProgramTask extends Model
{
    protected $table = 'internship_program_tasks';

    protected $fillable = [
        'program_id', 'day_number', 'title', 'objective', 'study_note', 'instructions_json', 'resources_json',
        'estimated_hours', 'tools', 'difficulty', 'submission_requirements', 'rubric_json',
        'pass_mark', 'requires_supervisor_approval', 'is_active',
    ];

    protected $casts = [
        'estimated_hours' => 'float',
        'pass_mark' => 'integer',
        'requires_supervisor_approval' => 'boolean',
        'is_active' => 'boolean',
        'day_number' => 'integer',
    ];

    public function program()
    {
        return $this->belongsTo(InternshipProgram::class, 'program_id');
    }

    public function instructions()
    {
        $raw = $this->instructions_json;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : (array_filter([(string) $raw]));
    }

    public function rubric()
    {
        $raw = $this->rubric_json;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

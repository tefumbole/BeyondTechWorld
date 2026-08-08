<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipProgram extends Model
{
    protected $table = 'internship_programs';

    protected $fillable = [
        'code', 'name', 'description', 'version', 'status', 'duration_tasks',
        'discipline', 'prerequisites', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_tasks' => 'integer',
    ];

    public function tasks()
    {
        return $this->hasMany(InternshipProgramTask::class, 'program_id')->orderBy('day_number');
    }

    public function enrolments()
    {
        return $this->hasMany(InternshipEnrolment::class, 'program_id');
    }

    public function isPublished()
    {
        return $this->status === 'published' && $this->is_active;
    }

    /**
     * Candidate-facing label (never advertise the full 180-day bank length).
     */
    public function displayName()
    {
        $name = (string) $this->name;
        $name = preg_replace('/^180[- ]Day\s+/i', '', $name);
        $name = preg_replace('/\s+Internship$/i', '', $name);

        return trim($name) !== '' ? trim($name) : $this->name;
    }
}

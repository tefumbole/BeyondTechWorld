<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipProgram extends Model
{
    protected $table = 'internship_programs';

    protected $fillable = [
        'code', 'name', 'description', 'version', 'status', 'duration_tasks', 'max_students',
        'discipline', 'prerequisites', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_tasks' => 'integer',
        'max_students' => 'integer',
    ];

    /** Active/paused enrolments + selected/hired apps with no enrolment yet (seat hold). */
    public function filledSeatsCount()
    {
        $enrolmentCount = InternshipEnrolment::where('program_id', $this->id)
            ->whereIn('status', ['active', 'paused'])
            ->count();

        $unplacedSelected = Application::query()
            ->where('internship_program_id', $this->id)
            ->whereIn('status', [Application::STATUS_SELECTED, 'shortlisted', Application::STATUS_HIRED])
            ->whereNotExists(function ($w) {
                $w->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('internship_enrolments as ie')
                    ->whereColumn('ie.application_id', 'applications.id')
                    ->whereIn('ie.status', ['active', 'paused', 'completed']);
            })
            ->count();

        return $enrolmentCount + $unplacedSelected;
    }

    public function remainingSeats()
    {
        if ($this->max_students === null) {
            return null;
        }

        return max(0, (int) $this->max_students - $this->filledSeatsCount());
    }

    public function hasCapacityForOneMore($excludingApplicationId = null)
    {
        if ($this->max_students === null) {
            return true;
        }
        $filled = $this->filledSeatsCount();
        if ($excludingApplicationId) {
            $already = Application::where('id', $excludingApplicationId)
                ->where('internship_program_id', $this->id)
                ->exists();
            if ($already) {
                return true;
            }
        }

        return $filled < (int) $this->max_students;
    }

    public function capacityLabel()
    {
        if ($this->max_students === null) {
            return $this->filledSeatsCount().' placed · unlimited';
        }

        return $this->filledSeatsCount().' / '.$this->max_students.' seats';
    }

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

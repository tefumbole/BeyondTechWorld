<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipEnrolment extends Model
{
    protected $table = 'internship_enrolments';

    protected $fillable = [
        'student_user_id', 'application_id', 'program_id', 'supervisor_id', 'supervisors_json',
        'start_date', 'planned_duration_days', 'start_curriculum_day', 'status',
        'current_task_order', 'completed_count', 'last_release_date', 'next_release_date',
        'completed_at', 'notes',
    ];

    protected $dates = ['start_date', 'last_release_date', 'next_release_date', 'completed_at'];

    protected $casts = [
        'planned_duration_days' => 'integer',
        'start_curriculum_day' => 'integer',
        'current_task_order' => 'integer',
        'completed_count' => 'integer',
    ];

    /**
     * Date the next task is scheduled for, when the supervisor has accepted the
     * previous submission and the release is waiting for the next working day.
     *
     * @return \Carbon\Carbon|null
     */
    public function releaseHeldUntil()
    {
        if (! $this->next_release_date) {
            return null;
        }

        $date = \Carbon\Carbon::parse($this->next_release_date)->startOfDay();

        return $date->isFuture() ? $date : null;
    }

    /** Directory refs (user:…, customer:…) for all supervisors. */
    public function supervisorRefs()
    {
        $raw = $this->supervisors_json;
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
        }

        return [];
    }

    /** ERP user IDs allowed to supervise this enrolment (primary + resolved multi). */
    public function supervisorUserIds()
    {
        $ids = [];
        if ($this->supervisor_id) {
            $ids[] = (int) $this->supervisor_id;
        }
        foreach ($this->supervisorRefs() as $ref) {
            if (strpos($ref, 'user:') === 0) {
                $ids[] = (int) substr($ref, 5);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function isSupervisedBy($userId)
    {
        return in_array((int) $userId, $this->supervisorUserIds(), true);
    }

    /** First curriculum day number this placement will receive (1–180). */
    public function startCurriculumDay()
    {
        $day = (int) ($this->start_curriculum_day ?: 1);

        return max(1, min(180, $day));
    }

    /** Max curriculum tasks this placement should complete (capped by remaining bank from start day). */
    public function plannedDurationDays()
    {
        $days = (int) ($this->planned_duration_days ?: 180);
        $days = max(1, min(180, $days));
        $maxPossible = 181 - $this->startCurriculumDay();

        return min($days, $maxPossible);
    }

    /** Last curriculum day number included in this placement. */
    public function endCurriculumDay()
    {
        return $this->startCurriculumDay() + $this->plannedDurationDays() - 1;
    }

    /** Next curriculum day to release (null if placement finished). */
    public function nextCurriculumDay()
    {
        // Advance by highest already-released day so a released-then-skipped day is
        // never repeated (completed_count only tracks accepted submissions).
        $maxReleased = (int) \App\InternshipTaskAssignment::where('enrolment_id', $this->id)
            ->whereNotNull('progression_day')
            ->max('progression_day');

        if ($maxReleased > 0) {
            $next = $maxReleased + 1;
        } else {
            $next = $this->startCurriculumDay() + (int) $this->completed_count;
        }

        if ($next > $this->endCurriculumDay()) {
            return null;
        }

        return $next;
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function program()
    {
        return $this->belongsTo(InternshipProgram::class, 'program_id');
    }

    public function assignments()
    {
        return $this->hasMany(InternshipTaskAssignment::class, 'enrolment_id');
    }

    public function currentOpenAssignment()
    {
        return $this->assignments()
            ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
            ->orderByDesc('id')
            ->first();
    }
}

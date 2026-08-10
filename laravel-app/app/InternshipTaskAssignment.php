<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipTaskAssignment extends Model
{
    protected $table = 'internship_task_assignments';

    protected $fillable = [
        'enrolment_id', 'program_task_id', 'progression_day', 'scheduled_work_date',
        'released_at', 'status', 'attempt_count', 'steps_progress_json',
        'whatsapp_sent_at', 'whatsapp_message_id',
    ];

    protected $dates = ['scheduled_work_date', 'released_at', 'whatsapp_sent_at'];

    protected $casts = [
        'progression_day' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function enrolment()
    {
        return $this->belongsTo(InternshipEnrolment::class, 'enrolment_id');
    }

    public function task()
    {
        return $this->belongsTo(InternshipProgramTask::class, 'program_task_id');
    }

    public function submissions()
    {
        return $this->hasMany(InternshipSubmission::class, 'assignment_id')->orderByDesc('attempt_no');
    }

    public function latestSubmission()
    {
        return $this->hasOne(InternshipSubmission::class, 'assignment_id')->latest('id');
    }

    /**
     * Checked step indices (0-based) from guide instructions.
     *
     * @return int[]
     */
    public function checkedStepIndices()
    {
        $raw = $this->steps_progress_json;
        if (is_array($raw)) {
            return array_values(array_unique(array_map('intval', $raw)));
        }
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $decoded)));
    }

    public function setCheckedStepIndices(array $indices)
    {
        $clean = array_values(array_unique(array_map('intval', $indices)));
        sort($clean);
        $this->steps_progress_json = json_encode($clean);

        return $this;
    }

    /**
     * @return array{total:int,done:int,percent:int,checked:int[]}
     */
    public function stepProgress()
    {
        $task = $this->relationLoaded('task') ? $this->task : $this->task()->first();
        $steps = $task ? $task->instructions() : [];
        $total = count($steps);
        $checked = $this->checkedStepIndices();
        if ($total > 0) {
            $checked = array_values(array_filter($checked, function ($i) use ($total) {
                return $i >= 0 && $i < $total;
            }));
        }
        $done = $total > 0 ? min(count($checked), $total) : 0;
        $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $percent,
            'checked' => $checked,
            'steps' => $steps,
        ];
    }
}

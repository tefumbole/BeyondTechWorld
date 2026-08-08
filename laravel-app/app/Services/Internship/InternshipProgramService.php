<?php

namespace App\Services\Internship;

use App\InternshipEnrolment;
use App\InternshipGrade;
use App\InternshipProgram;
use App\InternshipProgramTask;
use App\InternshipSubmission;
use App\InternshipSubmissionFile;
use App\InternshipTaskAssignment;
use App\Services\Messaging\NotificationRouter;
use App\Services\TimesheetService;
use App\Support\WhatsAppMessage;
use App\User;
use App\WorkingWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InternshipProgramService
{
    protected $timesheets;

    public function __construct(TimesheetService $timesheets)
    {
        $this->timesheets = $timesheets;
    }

    public function seedPath()
    {
        return database_path('data/internship/beyond_180_day_curriculum_seed.json');
    }

    public function importCurriculum($path = null, $commit = true)
    {
        $path = $path ?: $this->seedPath();
        if (! is_file($path)) {
            throw new \RuntimeException('Curriculum seed not found: '.$path);
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || empty($data['programs'])) {
            throw new \RuntimeException('Invalid curriculum JSON.');
        }

        $errors = [];
        $codes = [];
        foreach ($data['programs'] as $i => $prog) {
            $code = strtoupper(trim((string) ($prog['code'] ?? '')));
            if ($code === '' || isset($codes[$code])) {
                $errors[] = "Program #{$i}: duplicate or missing code.";
                continue;
            }
            $codes[$code] = true;
            $tasks = $prog['tasks'] ?? [];
            if (count($tasks) !== 180) {
                $errors[] = "{$code}: expected 180 tasks, got ".count($tasks);
            }
            $nums = array_map(function ($t) {
                return (int) ($t['day_number'] ?? 0);
            }, $tasks);
            sort($nums);
            if ($nums !== range(1, 180)) {
                $errors[] = "{$code}: day_number must be sequential 1–180.";
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'imported' => 0];
        }
        if (! $commit) {
            return ['ok' => true, 'errors' => [], 'imported' => 0, 'dry_run' => count($data['programs'])];
        }

        $imported = 0;
        DB::transaction(function () use ($data, &$imported) {
            foreach ($data['programs'] as $prog) {
                $code = strtoupper(trim($prog['code']));
                $version = (string) ($prog['version'] ?? '1.0');
                $program = InternshipProgram::updateOrCreate(
                    ['code' => $code, 'version' => $version],
                    [
                        'name' => $prog['name'] ?? $code,
                        'description' => $prog['description'] ?? null,
                        'status' => 'published',
                        'duration_tasks' => 180,
                        'discipline' => $prog['discipline'] ?? $code,
                        'prerequisites' => $prog['prerequisites'] ?? null,
                        'is_active' => true,
                    ]
                );
                foreach ($prog['tasks'] as $task) {
                    $day = (int) $task['day_number'];
                    $rubric = $task['marking_guide'] ?? $task['rubric'] ?? [];
                    InternshipProgramTask::updateOrCreate(
                        ['program_id' => $program->id, 'day_number' => $day],
                        [
                            'title' => $task['title'] ?? "Task {$day}",
                            'objective' => $task['objective'] ?? null,
                            'instructions_json' => json_encode($task['instructions'] ?? []),
                            'resources_json' => json_encode($task['resources'] ?? []),
                            'estimated_hours' => (float) ($task['estimated_hours'] ?? 8),
                            'tools' => $task['tools'] ?? null,
                            'difficulty' => $task['difficulty'] ?? null,
                            'submission_requirements' => $task['submission'] ?? null,
                            'rubric_json' => json_encode($rubric),
                            'pass_mark' => (int) ($task['pass_mark'] ?? 60),
                            'requires_supervisor_approval' => ! empty($task['requires_supervisor_approval']),
                            'is_active' => true,
                        ]
                    );
                }
                $imported++;
            }
        });

        return ['ok' => true, 'errors' => [], 'imported' => $imported];
    }

    public function isWorkingDate(User $user, Carbon $date)
    {
        $ww = WorkingWeek::where('user_id', $user->id)->first();
        if (! $ww) {
            // Default Mon–Fri until student configures
            $dow = strtolower($date->format('l'));

            return in_array($dow, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], true);
        }
        $day = strtolower($date->format('l'));

        return (bool) $ww->{$day};
    }

    public function nextWorkingDate(User $user, Carbon $from)
    {
        $d = $from->copy()->startOfDay();
        for ($i = 0; $i < 370; $i++) {
            if ($this->isWorkingDate($user, $d)) {
                return $d->copy();
            }
            $d->addDay();
        }

        return null;
    }

    public function enroll(array $data)
    {
        $program = InternshipProgram::findOrFail($data['program_id']);
        if (! $program->isPublished()) {
            throw new \RuntimeException('Program must be published.');
        }
        $taskCount = $program->tasks()->where('is_active', true)->count();
        if ($taskCount !== 180) {
            throw new \RuntimeException('Program must have exactly 180 active tasks.');
        }

        $startDay = (int) ($data['start_curriculum_day'] ?? 1);
        $startDay = max(1, min(180, $startDay));
        $planned = (int) ($data['planned_duration_days'] ?? 180);
        $planned = max(1, min(180, $planned));
        $planned = min($planned, 181 - $startDay);

        $supervisorRefs = $data['supervisor_refs'] ?? [];
        if (! is_array($supervisorRefs)) {
            $supervisorRefs = [];
        }
        $supervisorRefs = array_values(array_unique(array_filter(array_map('strval', $supervisorRefs))));

        return InternshipEnrolment::create([
            'student_user_id' => (int) $data['student_user_id'],
            'application_id' => $data['application_id'] ?? null,
            'program_id' => $program->id,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'supervisors_json' => ! empty($supervisorRefs) ? json_encode($supervisorRefs) : null,
            'start_date' => $data['start_date'] ?? Carbon::today()->toDateString(),
            'planned_duration_days' => $planned,
            'start_curriculum_day' => $startDay,
            'status' => 'active',
            'current_task_order' => 0,
            'completed_count' => 0,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update calendar start / curriculum start day / duration for a placement.
     * Curriculum start day may change freely before any task is released.
     * After release, supervisors may set the next curriculum day if nothing is in progress.
     */
    public function updatePlacement(InternshipEnrolment $enrolment, array $data)
    {
        if (! empty($data['start_date'])) {
            $enrolment->start_date = Carbon::parse($data['start_date'])->toDateString();
        }

        $hasAssignments = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->exists();
        $open = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
            ->exists();

        if (! $hasAssignments) {
            if (isset($data['start_curriculum_day'])) {
                $startDay = max(1, min(180, (int) $data['start_curriculum_day']));
                $enrolment->start_curriculum_day = $startDay;
            }
            if (isset($data['planned_duration_days'])) {
                $planned = max(1, min(180, (int) $data['planned_duration_days']));
                $enrolment->planned_duration_days = min($planned, 181 - $enrolment->startCurriculumDay());
            }
        } elseif (! $open && isset($data['next_curriculum_day'])) {
            $next = max(1, min(180, (int) $data['next_curriculum_day']));
            $start = $enrolment->startCurriculumDay();
            $end = $enrolment->endCurriculumDay();
            if ($next < $start || $next > $end) {
                throw new \RuntimeException(
                    "Next curriculum day must be between {$start} and {$end} for this placement."
                );
            }
            $enrolment->completed_count = $next - $start;
            $enrolment->current_task_order = max(0, $next - 1);
            $enrolment->last_release_date = null;
        } elseif (isset($data['start_curriculum_day']) || isset($data['next_curriculum_day'])) {
            throw new \RuntimeException(
                'Cannot change curriculum start while a task is in progress. Pause or finish the open task first.'
            );
        }

        if (isset($data['notes'])) {
            $enrolment->notes = $data['notes'];
        }
        if (array_key_exists('supervisor_id', $data)) {
            $enrolment->supervisor_id = $data['supervisor_id'] ?: null;
        }
        if (array_key_exists('supervisor_refs', $data)) {
            $refs = is_array($data['supervisor_refs']) ? $data['supervisor_refs'] : [];
            $refs = array_values(array_unique(array_filter(array_map('strval', $refs))));
            $enrolment->supervisors_json = ! empty($refs) ? json_encode($refs) : null;
        }

        $enrolment->save();

        return $enrolment->fresh();
    }

    /**
     * Idempotent release pass for all active enrolments (or one).
     */
    public function reconcileReleases($enrolmentId = null)
    {
        $today = Carbon::today();
        $q = InternshipEnrolment::with(['student', 'program'])
            ->where('status', 'active')
            ->where(function ($w) use ($today) {
                $w->whereNull('start_date')->orWhere('start_date', '<=', $today->toDateString());
            });
        if ($enrolmentId) {
            $q->where('id', $enrolmentId);
        }

        $released = 0;
        foreach ($q->get() as $enrolment) {
            try {
                if ($this->tryReleaseNext($enrolment, $today)) {
                    $released++;
                }
            } catch (\Throwable $e) {
                Log::warning('Internship release failed #'.$enrolment->id.': '.$e->getMessage());
            }
        }

        return $released;
    }

    public function tryReleaseNext(InternshipEnrolment $enrolment, Carbon $today = null)
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();
        $student = $enrolment->student;
        if (! $student) {
            return false;
        }

        $result = DB::transaction(function () use ($enrolment, $student, $today) {
            $enrolment = InternshipEnrolment::where('id', $enrolment->id)->lockForUpdate()->first();
            if (! $enrolment || $enrolment->status !== 'active') {
                return null;
            }

            $blocking = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
                ->exists();
            if ($blocking) {
                return null;
            }

            if ($enrolment->last_release_date
                && Carbon::parse($enrolment->last_release_date)->isSameDay($today)) {
                return null;
            }

            if (! $this->isWorkingDate($student, $today)) {
                return null;
            }

            $nextDay = $enrolment->nextCurriculumDay();
            if (! $nextDay) {
                return null;
            }

            $task = InternshipProgramTask::where('program_id', $enrolment->program_id)
                ->where('day_number', $nextDay)
                ->where('is_active', true)
                ->first();
            if (! $task) {
                return null;
            }

            $existing = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->where('program_task_id', $task->id)
                ->first();
            if ($existing) {
                if (in_array($existing->status, ['passed', 'skipped', 'available', 'in_progress', 'submitted', 'revision_required'], true)) {
                    return null;
                }
                if ($existing->status === 'locked') {
                    $existing->status = 'available';
                    $existing->scheduled_work_date = $today->toDateString();
                    $existing->released_at = now();
                    $existing->save();
                    $enrolment->current_task_order = $nextDay;
                    $enrolment->last_release_date = $today->toDateString();
                    $enrolment->save();

                    return ['enrolment_id' => $enrolment->id, 'assignment_id' => $existing->id, 'day' => $nextDay];
                }

                return null;
            }

            $assignment = InternshipTaskAssignment::create([
                'enrolment_id' => $enrolment->id,
                'program_task_id' => $task->id,
                'progression_day' => $nextDay,
                'scheduled_work_date' => $today->toDateString(),
                'released_at' => now(),
                'status' => 'available',
                'attempt_count' => 0,
            ]);

            $enrolment->current_task_order = $nextDay;
            $enrolment->last_release_date = $today->toDateString();
            $enrolment->save();

            $this->log($enrolment->id, 'task_released', null, [
                'assignment_id' => $assignment->id,
                'day' => $nextDay,
            ]);

            return ['enrolment_id' => $enrolment->id, 'assignment_id' => $assignment->id, 'day' => $nextDay];
        });

        if (! $result) {
            return false;
        }

        $enrolment = InternshipEnrolment::with(['student', 'program'])->find($result['enrolment_id']);
        $assignment = InternshipTaskAssignment::with('task')->find($result['assignment_id']);
        if ($enrolment && $assignment) {
            $this->notifyTaskReleased($enrolment, $assignment);
        }

        return true;
    }

    public function startAssignment(InternshipTaskAssignment $assignment, User $user)
    {
        if ((int) $assignment->enrolment->student_user_id !== (int) $user->id) {
            abort(403);
        }
        if (in_array($assignment->status, ['available', 'revision_required'], true)) {
            $assignment->status = 'in_progress';
            $assignment->save();
        }

        return $assignment;
    }

    public function submitAssignment(InternshipTaskAssignment $assignment, User $user, $description, array $uploadedFiles)
    {
        if ((int) $assignment->enrolment->student_user_id !== (int) $user->id) {
            abort(403);
        }
        if (! in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true)) {
            throw new \RuntimeException('This task cannot accept a submission right now.');
        }

        $attempt = ((int) $assignment->attempt_count) + 1;
        $submission = InternshipSubmission::create([
            'assignment_id' => $assignment->id,
            'student_user_id' => $user->id,
            'attempt_no' => $attempt,
            'description' => $description,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $dir = 'internship/submissions/'.$submission->id;
        foreach ($uploadedFiles as $file) {
            if (! $file) {
                continue;
            }
            $path = $file->store($dir, 'local');
            InternshipSubmissionFile::create([
                'submission_id' => $submission->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'checksum' => @hash_file('sha256', $file->getRealPath()) ?: null,
            ]);
        }

        $assignment->attempt_count = $attempt;
        $assignment->status = 'submitted';
        $assignment->save();

        $this->log($assignment->enrolment_id, 'submission_received', null, [
            'submission_id' => $submission->id,
            'attempt' => $attempt,
        ]);

        $this->notifySubmission($assignment->enrolment->fresh(['student', 'program', 'supervisor']), $assignment->fresh(['task']), $submission);

        return $submission;
    }

    public function gradeSubmission(InternshipSubmission $submission, User $grader, array $data)
    {
        $decision = $data['decision'] === 'revision_required' ? 'revision_required' : 'pass';
        $score = (int) ($data['score'] ?? 0);
        $rubric = $data['rubric_scores'] ?? [];
        if (! empty($rubric) && is_array($rubric)) {
            $score = array_sum(array_map('intval', $rubric));
        }

        $grade = InternshipGrade::create([
            'submission_id' => $submission->id,
            'grader_id' => $grader->id,
            'score' => max(0, min(100, $score)),
            'rubric_scores_json' => json_encode($rubric),
            'feedback' => $data['feedback'] ?? null,
            'decision' => $decision,
            'graded_at' => now(),
        ]);

        $assignment = $submission->assignment;
        $enrolment = $assignment->enrolment;
        $passMark = (int) ($assignment->task->pass_mark ?? 60);

        if ($decision === 'pass' && $grade->score >= $passMark) {
            $submission->status = 'passed';
            $submission->save();
            $assignment->status = 'passed';
            $assignment->save();
            $enrolment->completed_count = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->where('status', 'passed')
                ->count();
            if ($enrolment->completed_count >= $enrolment->plannedDurationDays()) {
                $enrolment->status = 'completed';
                $enrolment->completed_at = now();
            }
            $enrolment->save();
            $this->notifyPassed($enrolment->fresh(['student', 'program']), $assignment->fresh(['task']), $grade);
            if ($enrolment->status === 'completed') {
                $this->notifyCompleted($enrolment);
            }
        } else {
            $submission->status = 'revision_required';
            $submission->save();
            $assignment->status = 'revision_required';
            $assignment->save();
            $this->notifyRevision($enrolment->fresh(['student', 'program']), $assignment->fresh(['task']), $grade);
        }

        $this->log($enrolment->id, 'graded', null, [
            'submission_id' => $submission->id,
            'decision' => $decision,
            'score' => $grade->score,
        ]);

        return $grade;
    }

    public function pendingForStudent(User $user)
    {
        $enrolment = InternshipEnrolment::with(['program', 'supervisor', 'assignments.task'])
            ->where('student_user_id', $user->id)
            ->whereIn('status', ['active', 'paused', 'completed'])
            ->orderByDesc('id')
            ->first();
        if (! $enrolment) {
            return null;
        }
        $open = $enrolment->currentOpenAssignment();
        if ($open) {
            $open->load('task', 'latestSubmission');
        }

        return [
            'enrolment' => $enrolment,
            'assignment' => $open,
        ];
    }

    protected function notifyTaskReleased(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment)
    {
        $student = $enrolment->student;
        $task = $assignment->task;
        if (! $student || ! $task) {
            return;
        }
        $key = 'task_released:'.$assignment->id.':student:'.$student->id;
        if ($this->alreadyNotified($key)) {
            return;
        }

        $url = url('/admin/internship/student/task/'.$assignment->id);
        $msg = WhatsAppMessage::statusBlock('📚', 'Internship Task');
        $msg .= WhatsAppMessage::greeting($student->name);
        $msg .= "Your internship task for today is ready.\n\n";
        $msg .= WhatsAppMessage::bullet('Program', optional($enrolment->program)->displayName() ?? '');
        $msg .= WhatsAppMessage::bullet('Task', '#'.$assignment->progression_day.' — '.$task->title);
        $msg .= WhatsAppMessage::bullet('Date', (string) $assignment->scheduled_work_date);
        $msg .= WhatsAppMessage::actionLink('Open internship dashboard', $url);
        $msg .= "Complete and submit from your dashboard. Only one task is released per working day.";
        $msg .= WhatsAppMessage::footer();

        $result = $this->sendWhatsApp($student, $msg, $key, 'task_released');
        if (! empty($result['success'])) {
            $assignment->whatsapp_sent_at = now();
            $assignment->whatsapp_message_id = $result['sid'] ?? $result['provider_sid'] ?? null;
            $assignment->save();
        }
    }

    protected function notifySubmission(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, InternshipSubmission $submission)
    {
        $supervisor = $enrolment->supervisor;
        if (! $supervisor) {
            return;
        }
        $key = 'submission:'.$submission->id.':supervisor:'.$supervisor->id;
        if ($this->alreadyNotified($key)) {
            return;
        }
        $url = url('/admin/internship/supervisor/submissions/'.$submission->id);
        $msg = WhatsAppMessage::statusBlock('📝', 'Internship Submission');
        $msg .= WhatsAppMessage::greeting($supervisor->name);
        $msg .= "A student submitted internship work for review.\n\n";
        $msg .= WhatsAppMessage::bullet('Student', optional($enrolment->student)->name);
        $msg .= WhatsAppMessage::bullet('Program', optional($enrolment->program)->displayName());
        $msg .= WhatsAppMessage::bullet('Task', '#'.$assignment->progression_day.' — '.optional($assignment->task)->title);
        $msg .= WhatsAppMessage::bullet('Submitted', optional($submission->submitted_at)->format('d M Y H:i') ?: now()->format('d M Y H:i'));
        $msg .= WhatsAppMessage::actionLink('Grade submission', $url);
        $msg .= WhatsAppMessage::footer();
        $this->sendWhatsApp($supervisor, $msg, $key, 'submission_received');
    }

    protected function notifyRevision(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, InternshipGrade $grade)
    {
        $student = $enrolment->student;
        if (! $student) {
            return;
        }
        $key = 'revision:'.$grade->id.':student:'.$student->id;
        if ($this->alreadyNotified($key)) {
            return;
        }
        $url = url('/admin/internship/student/task/'.$assignment->id);
        $msg = WhatsAppMessage::statusBlock('✏️', 'Revision Required');
        $msg .= WhatsAppMessage::greeting($student->name);
        $msg .= "Your supervisor requested a revision on your internship task.\n\n";
        $msg .= WhatsAppMessage::bullet('Task', '#'.$assignment->progression_day.' — '.optional($assignment->task)->title);
        $msg .= WhatsAppMessage::bullet('Score', (string) $grade->score);
        if ($grade->feedback) {
            $msg .= "\n*Feedback:*\n".$grade->feedback."\n";
        }
        $msg .= WhatsAppMessage::actionLink('Update and resubmit', $url);
        $msg .= WhatsAppMessage::footer();
        $this->sendWhatsApp($student, $msg, $key, 'revision_requested');
    }

    protected function notifyPassed(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, InternshipGrade $grade)
    {
        $student = $enrolment->student;
        if (! $student) {
            return;
        }
        $key = 'passed:'.$grade->id.':student:'.$student->id;
        if ($this->alreadyNotified($key)) {
            return;
        }
        $msg = WhatsAppMessage::statusBlock('✅', 'Task Passed');
        $msg .= WhatsAppMessage::greeting($student->name);
        $msg .= "Great work — your internship task was passed.\n\n";
        $msg .= WhatsAppMessage::bullet('Task', '#'.$assignment->progression_day.' — '.optional($assignment->task)->title);
        $msg .= WhatsAppMessage::bullet('Score', $grade->score.'/100');
        $msg .= WhatsAppMessage::bullet('Progress', $enrolment->completed_count.'/'.$enrolment->plannedDurationDays());
        if ($enrolment->completed_count < $enrolment->plannedDurationDays()) {
            $msg .= "\nYour next task will be released on your next configured working day.";
        }
        $msg .= WhatsAppMessage::footer();
        $this->sendWhatsApp($student, $msg, $key, 'task_passed');
    }

    protected function notifyCompleted(InternshipEnrolment $enrolment)
    {
        $student = $enrolment->student;
        if (! $student) {
            return;
        }
        $key = 'completed:'.$enrolment->id.':student:'.$student->id;
        if ($this->alreadyNotified($key)) {
            return;
        }
        $days = $enrolment->plannedDurationDays();
        $msg = WhatsAppMessage::statusBlock('🎓', 'Internship Completed');
        $msg .= WhatsAppMessage::greeting($student->name);
        $msg .= "Congratulations! You completed your {$days}-day internship program.\n\n";
        $msg .= WhatsAppMessage::bullet('Program', optional($enrolment->program)->displayName());
        $msg .= WhatsAppMessage::actionLink('View portfolio', url('/admin/internship/student'));
        $msg .= WhatsAppMessage::footer();
        $this->sendWhatsApp($student, $msg, $key, 'program_completed');
    }

    protected function alreadyNotified($key)
    {
        return DB::table('internship_notification_logs')->where('idempotency_key', $key)->where('status', 'sent')->exists();
    }

    protected function sendWhatsApp(User $user, $message, $idempotencyKey, $event)
    {
        $phone = $user->phone ?? $user->phone_number ?? null;
        $row = [
            'idempotency_key' => $idempotencyKey,
            'event' => $event,
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'phone' => $phone,
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        try {
            DB::table('internship_notification_logs')->insert($row);
        } catch (\Throwable $e) {
            // duplicate key — already attempted
            return ['success' => false, 'error' => 'duplicate'];
        }

        if (! $phone) {
            DB::table('internship_notification_logs')->where('idempotency_key', $idempotencyKey)->update([
                'status' => 'failed',
                'error' => 'No phone on user',
                'updated_at' => now(),
            ]);

            return ['success' => false, 'error' => 'No phone'];
        }

        try {
            $result = app(NotificationRouter::class)->sendWhatsAppText($phone, $message);
            DB::table('internship_notification_logs')->where('idempotency_key', $idempotencyKey)->update([
                'status' => ! empty($result['success']) ? 'sent' : 'failed',
                'provider_message_id' => $result['sid'] ?? $result['provider_sid'] ?? null,
                'error' => $result['error'] ?? null,
                'updated_at' => now(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            DB::table('internship_notification_logs')->where('idempotency_key', $idempotencyKey)->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function log($enrolmentId, $event, $old = null, $new = null)
    {
        try {
            DB::table('internship_activity_logs')->insert([
                'actor_id' => Auth::id(),
                'enrolment_id' => $enrolmentId,
                'event' => $event,
                'old_values' => $old ? json_encode($old) : null,
                'new_values' => $new ? json_encode($new) : null,
                'ip' => request() ? request()->ip() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
        }
    }
}

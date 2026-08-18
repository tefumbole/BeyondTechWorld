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
use App\Support\InternCompliance;
use App\Support\InternshipHandbook;
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
                            'study_note' => $task['study_note'] ?? null,
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
        // One schedule per student — no silent Mon–Fri default; student must save Working Week.
        if (! InternCompliance::workingWeekConfigured($user)) {
            return false;
        }

        $ww = WorkingWeek::where('user_id', $user->id)->first();
        if (! $ww) {
            return false;
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

    /**
     * Preview which active placements would receive a new daily task on $date
     * (without creating assignments). Used by Task Manager upcoming tabs.
     *
     * @return \Illuminate\Support\Collection<int, array{
     *   enrolment:InternshipEnrolment,
     *   student:?User,
     *   program:?InternshipProgram,
     *   task:?InternshipProgramTask,
     *   progression_day:int,
     *   scheduled_work_date:string,
     *   source:string
     * }>
     */
    public function previewUpcomingReleases(Carbon $date)
    {
        $date = $date->copy()->startOfDay();
        $dateStr = $date->toDateString();
        $out = collect();

        $enrolments = InternshipEnrolment::with(['student', 'program', 'supervisor'])
            ->where('status', 'active')
            ->where(function ($w) use ($dateStr) {
                $w->whereNull('start_date')->orWhere('start_date', '<=', $dateStr);
            })
            ->get();

        foreach ($enrolments as $enrolment) {
            $student = $enrolment->student;
            if (! $student) {
                continue;
            }

            // Must have a saved personal Working Week before any preview/release.
            if (! InternCompliance::workingWeekConfigured($student)) {
                continue;
            }

            $blocking = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereIn('status', ['available', 'in_progress', 'revision_required'])
                ->exists();
            if ($blocking) {
                continue;
            }

            if ($enrolment->last_release_date
                && Carbon::parse($enrolment->last_release_date)->isSameDay($date)) {
                continue;
            }

            if (! $this->isWorkingDate($student, $date)) {
                continue;
            }

            $nextDay = $enrolment->nextCurriculumDay();
            if (! $nextDay) {
                continue;
            }

            $task = InternshipProgramTask::where('program_id', $enrolment->program_id)
                ->where('day_number', $nextDay)
                ->where('is_active', true)
                ->first();
            if (! $task) {
                continue;
            }

            $existing = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->where('program_task_id', $task->id)
                ->first();
            if ($existing && in_array($existing->status, ['passed', 'skipped', 'available', 'in_progress', 'submitted', 'revision_required'], true)) {
                continue;
            }

            $out->push([
                'enrolment' => $enrolment,
                'student' => $student,
                'program' => $enrolment->program,
                'supervisor' => $enrolment->supervisor,
                'task' => $task,
                'progression_day' => $nextDay,
                'scheduled_work_date' => $dateStr,
                'source' => 'scheduled',
                'assignment' => $existing,
            ]);
        }

        return $out;
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

    public function tryReleaseNext(InternshipEnrolment $enrolment, Carbon $today = null, $forceSameDay = false)
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();
        $student = $enrolment->student;
        if (! $student) {
            return false;
        }

        $releasedCount = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereNotNull('released_at')
            ->count();
        $isFirstTask = $releasedCount === 0;

        // First task after admission: allow release even before Working Week is configured.
        // Later tasks still require a personal Working Week.
        if (! $isFirstTask && ! InternCompliance::workingWeekConfigured($student)) {
            return false;
        }

        $result = DB::transaction(function () use ($enrolment, $student, $today, $forceSameDay, $isFirstTask) {
            $enrolment = InternshipEnrolment::where('id', $enrolment->id)->lockForUpdate()->first();
            if (! $enrolment || $enrolment->status !== 'active') {
                return null;
            }

            // Only block when the student still has an open (unsubmitted) task.
            // Submitted tasks may await grading while the next day is released.
            $blocking = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereIn('status', ['available', 'in_progress', 'revision_required'])
                ->exists();
            if ($blocking) {
                return null;
            }

            if (! $forceSameDay && $enrolment->last_release_date
                && Carbon::parse($enrolment->last_release_date)->isSameDay($today)) {
                return null;
            }

            if (! $isFirstTask && ! $this->isWorkingDate($student, $today)) {
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

        // After Task N is submitted, immediately release Task N+1 (same account / placement).
        try {
            $enrolment = $assignment->enrolment->fresh(['student', 'program']);
            if ($enrolment) {
                $this->tryReleaseNext($enrolment, Carbon::today(), true);
            }
        } catch (\Throwable $e) {
            Log::warning('Post-submit next task release failed: '.$e->getMessage(), [
                'assignment_id' => $assignment->id,
            ]);
        }

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
        $this->dispatchTaskReleasedWhatsApp($enrolment, $assignment, false);
    }

    /**
     * Resend today's task WhatsApp to the student (and optionally supervisors).
     * Uses a unique idempotency key so prior sends do not block delivery.
     *
     * @return array{success:bool,error?:string,student?:array,supervisors?:int}
     */
    public function resendTaskReleased(InternshipTaskAssignment $assignment, $includeSupervisors = true)
    {
        $assignment->loadMissing(['task', 'enrolment.program', 'enrolment.student']);
        $enrolment = $assignment->enrolment;
        if (! $enrolment || ! $assignment->task) {
            return ['success' => false, 'error' => 'Assignment or task not found.'];
        }
        if (! in_array($assignment->status, ['available', 'in_progress', 'revision_required', 'submitted'], true)) {
            return ['success' => false, 'error' => 'Only open/released tasks can be resent.'];
        }

        $result = $this->dispatchTaskReleasedWhatsApp($enrolment, $assignment, true);
        if ($includeSupervisors) {
            $this->notifySupervisorsTaskReleased($enrolment, $assignment, true);
        }

        return $result;
    }

    /**
     * @return array{success:bool,error?:string,student?:array}
     */
    protected function dispatchTaskReleasedWhatsApp(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, $forceResend = false)
    {
        $student = $enrolment->student;
        $task = $assignment->task;
        if (! $student || ! $task) {
            return ['success' => false, 'error' => 'Student or task missing.'];
        }

        $suffix = $forceResend ? ':resend:'.now()->format('YmdHis') : '';
        $key = 'task_released:'.$assignment->id.':student:'.$student->id.$suffix;
        if (! $forceResend && $this->alreadyNotified($key)) {
            $this->notifySupervisorsTaskReleased($enrolment, $assignment, false);

            return ['success' => true, 'student' => ['skipped' => true]];
        }

        $url = url('/admin/internship/student/task/'.$assignment->id);
        $taskLabel = '#'.$assignment->progression_day.' — '.$task->title;
        $program = $enrolment->program;
        $handbookPath = $program ? InternshipHandbook::absolutePath($program, $task) : null;
        $handbookName = $program ? InternshipHandbook::downloadName($program, $task) : null;
        $hasHandbook = $handbookPath && is_file($handbookPath);

        $msg = WhatsAppMessage::internshipDailyTask(
            $student->name,
            optional($program)->displayName() ?? '',
            $taskLabel,
            (string) $assignment->scheduled_work_date,
            $url,
            $task->instructions(),
            $hasHandbook
        );

        $result = $this->sendWhatsApp($student, $msg, $key, $forceResend ? 'task_released_resend' : 'task_released');
        if (! empty($result['success'])) {
            $assignment->whatsapp_sent_at = now();
            $assignment->whatsapp_message_id = $result['sid'] ?? $result['provider_sid'] ?? $result['msg_id'] ?? null;
            $assignment->save();
        }

        // Word handbook so interns can work even if they cannot log into the ERP.
        $handbookResult = null;
        if ($hasHandbook) {
            $docKey = 'task_handbook:'.$assignment->id.':student:'.$student->id.$suffix;
            if ($forceResend || ! $this->alreadyNotified($docKey)) {
                $handbookResult = $this->sendWhatsAppDocument(
                    $student,
                    $handbookPath,
                    $handbookName ?: basename($handbookPath),
                    $docKey,
                    $forceResend ? 'task_handbook_student_resend' : 'task_handbook_student',
                    'Instruction handbook — '.$taskLabel
                );
            } else {
                $handbookResult = ['success' => true, 'skipped' => true];
            }
        }

        if (! $forceResend) {
            $this->notifySupervisorsTaskReleased($enrolment, $assignment, false);
        }

        $ok = ! empty($result['success']);
        $error = $result['error'] ?? null;
        if ($ok && $hasHandbook && is_array($handbookResult) && empty($handbookResult['success']) && empty($handbookResult['skipped'])) {
            $ok = false;
            $error = 'WhatsApp text sent, but Word handbook failed: '.($handbookResult['error'] ?? 'upload/send error');
        }

        return [
            'success' => $ok,
            'error' => $error,
            'student' => $result,
            'handbook' => $handbookResult,
        ];
    }

    /**
     * Send supervisors a copy of the released task (text + instruction handbook DOCX).
     */
    protected function notifySupervisorsTaskReleased(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, $forceResend = false)
    {
        $task = $assignment->task;
        $program = $enrolment->program;
        if (! $task) {
            return;
        }

        $supervisorIds = $enrolment->supervisorUserIds();
        if (empty($supervisorIds)) {
            return;
        }

        $taskLabel = '#'.$assignment->progression_day.' — '.$task->title;
        $dashboardUrl = url('/admin/internship/supervisor');
        $handbookPath = $program ? InternshipHandbook::absolutePath($program, $task) : null;
        $handbookName = $program ? InternshipHandbook::downloadName($program, $task) : null;
        $suffix = $forceResend ? ':resend:'.now()->format('YmdHis') : '';

        foreach ($supervisorIds as $supervisorId) {
            $supervisor = User::where('is_deleted', false)->find($supervisorId);
            if (! $supervisor) {
                continue;
            }

            $textKey = 'task_released:'.$assignment->id.':supervisor:'.$supervisor->id.$suffix;
            if ($forceResend || ! $this->alreadyNotified($textKey)) {
                // Respect Wasender 1-message / 5s account protection after student messages.
                usleep(5500000);
                $msg = WhatsAppMessage::internshipSupervisorTaskCopy(
                    $supervisor->name,
                    optional($enrolment->student)->name,
                    $program ? $program->displayName() : '',
                    $taskLabel,
                    (string) $assignment->scheduled_work_date,
                    $dashboardUrl
                );
                $this->sendWhatsApp(
                    $supervisor,
                    $msg,
                    $textKey,
                    $forceResend ? 'task_released_supervisor_resend' : 'task_released_supervisor'
                );
            }

            if ($handbookPath && is_file($handbookPath)) {
                $docKey = 'task_handbook:'.$assignment->id.':supervisor:'.$supervisor->id.$suffix;
                if ($forceResend || ! $this->alreadyNotified($docKey)) {
                    $this->sendWhatsAppDocument(
                        $supervisor,
                        $handbookPath,
                        $handbookName ?: basename($handbookPath),
                        $docKey,
                        $forceResend ? 'task_handbook_supervisor_resend' : 'task_handbook_supervisor',
                        'Instruction handbook — '.$taskLabel
                    );
                }
            }
        }
    }

    /**
     * Persist student checklist progress for guide instruction points.
     *
     * @param  int[]  $checkedIndices
     * @return array{total:int,done:int,percent:int,checked:int[]}
     */
    public function updateAssignmentStepProgress(InternshipTaskAssignment $assignment, array $checkedIndices)
    {
        $assignment->loadMissing('task');
        $total = count($assignment->task ? $assignment->task->instructions() : []);
        $clean = [];
        foreach ($checkedIndices as $i) {
            $i = (int) $i;
            if ($i >= 0 && ($total === 0 || $i < $total)) {
                $clean[] = $i;
            }
        }
        $assignment->setCheckedStepIndices($clean);
        $assignment->save();

        return $assignment->stepProgress();
    }

    protected function notifySubmission(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, InternshipSubmission $submission)
    {
        $supervisorIds = $enrolment->supervisorUserIds();
        if (empty($supervisorIds) && $enrolment->supervisor_id) {
            $supervisorIds = [(int) $enrolment->supervisor_id];
        }
        if (empty($supervisorIds)) {
            return;
        }

        $url = url('/admin/internship/supervisor/submissions/'.$submission->id);
        foreach ($supervisorIds as $supervisorId) {
            $supervisor = User::where('is_deleted', false)->find($supervisorId);
            if (! $supervisor) {
                continue;
            }
            $key = 'submission:'.$submission->id.':supervisor:'.$supervisor->id;
            if ($this->alreadyNotified($key)) {
                continue;
            }
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

    /**
     * When ERP user.phone is empty, recover WhatsApp number from the linked application.
     */
    protected function resolveFallbackPhoneForUser(User $user)
    {
        try {
            $enrolment = InternshipEnrolment::where('student_user_id', $user->id)
                ->whereNotNull('application_id')
                ->orderByDesc('id')
                ->first();
            if (! $enrolment || ! $enrolment->application_id) {
                return null;
            }
            $app = \App\Application::find($enrolment->application_id);
            if (! $app) {
                return null;
            }
            $raw = $app->whatsapp_number ?: $app->phone;
            if (! $raw) {
                return null;
            }

            return app(\App\Services\BeyondWasenderService::class)->formatPhone($raw) ?: $raw;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function sendWhatsApp(User $user, $message, $idempotencyKey, $event)
    {
        $phone = $user->phone ?? $user->phone_number ?? null;
        if (! $phone) {
            $phone = $this->resolveFallbackPhoneForUser($user);
            if ($phone) {
                try {
                    $user->phone = $phone;
                    $user->save();
                } catch (\Throwable $e) {
                }
            }
        }
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

    protected function sendWhatsAppDocument(User $user, $localPath, $fileName, $idempotencyKey, $event, $caption = null)
    {
        if ($this->alreadyNotified($idempotencyKey)) {
            return ['success' => true, 'skipped' => true];
        }

        $phone = $user->phone ?? $user->phone_number ?? null;
        $row = [
            'idempotency_key' => $idempotencyKey,
            'event' => $event,
            'user_id' => $user->id,
            'channel' => 'whatsapp_document',
            'phone' => $phone,
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        try {
            DB::table('internship_notification_logs')->insert($row);
        } catch (\Throwable $e) {
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
            // Wasender account protection: only 1 message every 5 seconds.
            usleep(5500000);
            $result = app(NotificationRouter::class)->sendWhatsAppDocument($phone, $localPath, $fileName, $caption);
            DB::table('internship_notification_logs')->where('idempotency_key', $idempotencyKey)->update([
                'status' => ! empty($result['success']) ? 'sent' : 'failed',
                'provider_message_id' => $result['msg_id'] ?? $result['sid'] ?? null,
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
            Log::warning('[internship] supervisor handbook WhatsApp failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
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

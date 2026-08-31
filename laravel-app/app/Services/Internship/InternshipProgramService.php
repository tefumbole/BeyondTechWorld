<?php

namespace App\Services\Internship;

use App\InternshipDraftFile;
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
use App\Support\InternshipRubric;
use App\Support\WhatsAppMessage;
use App\User;
use App\WorkingWeek;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InternshipProgramService
{
    /** Delivery attempts allowed per notification before it is left alone. */
    const MAX_NOTIFICATION_ATTEMPTS = 5;

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

    /**
     * True when the student's timetable for $date has already started, so a task
     * released today lands inside their working hours instead of overnight.
     * Only today is time-gated; other dates are day-level decisions.
     */
    public function releaseWindowOpen(User $user, Carbon $date)
    {
        if (! $date->copy()->startOfDay()->isSameDay(Carbon::today())) {
            return true;
        }

        $ww = WorkingWeek::where('user_id', $user->id)->first();
        if (! $ww) {
            return false;
        }

        $day = strtolower($date->format('l'));
        $start = $ww->{$day.'_start'} ?: '08:00';
        try {
            $startAt = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString().' '.substr($start, 0, 5));
        } catch (\Throwable $e) {
            $startAt = $date->copy()->startOfDay()->setTime(8, 0, 0);
        }

        return now()->greaterThanOrEqualTo($startAt);
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
                ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
                ->exists();
            if ($blocking) {
                continue;
            }

            if ($enrolment->last_release_date
                && Carbon::parse($enrolment->last_release_date)->isSameDay($date)) {
                continue;
            }

            if ($enrolment->next_release_date
                && Carbon::parse($enrolment->next_release_date)->startOfDay()->greaterThan($date)) {
                continue;
            }

            if (! $this->isWorkingDate($student, $date)) {
                continue;
            }

            if (! $this->releaseWindowOpen($student, $date)) {
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
                // Leftover next-working-day holds (from before immediate post-grade
                // release) should catch up now so the intern does not miss a day.
                $unstick = (bool) $enrolment->next_release_date;
                if ($this->tryReleaseNext($enrolment, $today, $unstick, $unstick)) {
                    $released++;
                }
            } catch (\Throwable $e) {
                Log::warning('Internship release failed #'.$enrolment->id.': '.$e->getMessage());
            }
        }

        return $released;
    }

    /**
     * WhatsApp interns who finished a working day without logging hours.
     * One reminder per intern per missing date (idempotency key in the notification log).
     *
     * @return int reminders sent
     */
    public function remindMissingTimesheets()
    {
        $sent = 0;
        $enrolments = InternshipEnrolment::with('student')
            ->whereIn('status', ['active', 'paused'])
            ->get();

        foreach ($enrolments as $enrolment) {
            $student = $enrolment->student;
            if (! $student || ! InternCompliance::workingWeekConfigured($student)) {
                continue;
            }

            $missing = InternCompliance::missingTimesheetDate($student);
            if (! $missing) {
                continue;
            }

            $key = 'timesheet_reminder:'.$student->id.':'.$missing;
            if ($this->alreadyNotified($key)) {
                continue;
            }

            $assignment = InternshipTaskAssignment::with('task')
                ->where('enrolment_id', $enrolment->id)
                ->whereDate('scheduled_work_date', $missing)
                ->orderByDesc('id')
                ->first();
            $taskLabel = $assignment
                ? '#'.$assignment->progression_day.' — '.optional($assignment->task)->title
                : null;

            $params = ['date' => $missing, 'intern' => 1];
            if ($assignment) {
                $params['assignment'] = $assignment->id;
            }

            $msg = WhatsAppMessage::internshipTimesheetReminder(
                $student->name,
                Carbon::parse($missing)->format('D d M Y'),
                $taskLabel,
                route('timesheet.fill', $params)
            );

            $result = $this->sendWhatsApp($student, $msg, $key, 'timesheet_reminder');
            if (! empty($result['success'])) {
                $sent++;
            }

            // Wasender account protection: one message every 5 seconds.
            usleep(5500000);
        }

        return $sent;
    }

    public function tryReleaseNext(InternshipEnrolment $enrolment, Carbon $today = null, $forceSameDay = false, $immediate = false)
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();
        $forceSameDay = $forceSameDay || $immediate;
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

        $result = DB::transaction(function () use ($enrolment, $student, $today, $forceSameDay, $immediate, $isFirstTask) {
            $enrolment = InternshipEnrolment::where('id', $enrolment->id)->lockForUpdate()->first();
            if (! $enrolment || $enrolment->status !== 'active') {
                return null;
            }

            // Nothing releases while the student has work in hand — including a
            // submission awaiting grading. The next task follows acceptance.
            $blocking = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
                ->exists();
            if ($blocking) {
                return null;
            }

            if (! $forceSameDay && $enrolment->last_release_date
                && Carbon::parse($enrolment->last_release_date)->isSameDay($today)) {
                return null;
            }

            // A scheduled hold from an accepted submission is honoured even by
            // same-day paths, so nothing arrives before the planned working day —
            // unless this is an immediate post-grading release (supervisor delay
            // must not make the intern miss a curriculum day).
            if (! $immediate && $enrolment->next_release_date
                && Carbon::parse($enrolment->next_release_date)->startOfDay()->greaterThan($today)) {
                return null;
            }

            if (! $isFirstTask && ! $immediate && ! $this->isWorkingDate($student, $today)) {
                return null;
            }

            if (! $isFirstTask && ! $forceSameDay && ! $this->releaseWindowOpen($student, $today)) {
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

            // Faster students may start the next task now; hours are still due
            // on the next working day (not today, and never on a day off).
            $timesheetDate = $today->copy();
            if ($immediate && ! $isFirstTask) {
                $nextTs = $this->nextWorkingDate($student, $today->copy()->addDay());
                if ($nextTs) {
                    $timesheetDate = $nextTs;
                }
            }
            $workDateStr = $timesheetDate->toDateString();

            $existing = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->where('program_task_id', $task->id)
                ->first();
            if ($existing) {
                if (in_array($existing->status, ['passed', 'skipped', 'available', 'in_progress', 'submitted', 'revision_required'], true)) {
                    return null;
                }
                if ($existing->status === 'locked') {
                    $existing->status = 'available';
                    $existing->scheduled_work_date = $workDateStr;
                    $existing->released_at = now();
                    $existing->save();
                    $enrolment->current_task_order = $nextDay;
                    $enrolment->last_release_date = $today->toDateString();
                    $enrolment->next_release_date = null;
                    $enrolment->save();

                    return ['enrolment_id' => $enrolment->id, 'assignment_id' => $existing->id, 'day' => $nextDay];
                }

                return null;
            }

            $assignment = InternshipTaskAssignment::create([
                'enrolment_id' => $enrolment->id,
                'program_task_id' => $task->id,
                'progression_day' => $nextDay,
                'scheduled_work_date' => $workDateStr,
                'released_at' => now(),
                'status' => 'available',
                'attempt_count' => 0,
            ]);

            $enrolment->current_task_order = $nextDay;
            $enrolment->last_release_date = $today->toDateString();
            $enrolment->next_release_date = null;
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

    /**
     * @param  array<int, \Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[]|array{file?:mixed,caption?:string}>  $uploadedFiles
     */
    public function submitAssignment(InternshipTaskAssignment $assignment, User $user, $description, array $uploadedFiles)
    {
        if ((int) $assignment->enrolment->student_user_id !== (int) $user->id) {
            abort(403);
        }
        if (! in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true)) {
            throw new \RuntimeException('This task cannot accept a submission right now.');
        }

        $items = $this->normalizeEvidenceItems($uploadedFiles);
        $drafts = InternshipDraftFile::where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->orderBy('slot_index')
            ->orderBy('id')
            ->get();
        if (count($items) < 1 && $drafts->isEmpty()) {
            throw new \RuntimeException('Attach at least one file of the finished work.');
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
        $hasCaption = Schema::hasColumn('internship_submission_files', 'caption');
        $index = 0;
        foreach ($drafts as $draft) {
            $this->copyDraftToSubmission($draft, $submission, $dir, $hasCaption, $index);
            $index++;
        }
        foreach ($items as $item) {
            $file = $item['file'];
            $path = $file->store($dir, 'local');
            $row = [
                'submission_id' => $submission->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'checksum' => @hash_file('sha256', $file->getRealPath()) ?: null,
            ];
            if ($hasCaption) {
                $row['caption'] = $item['caption'] !== '' ? $item['caption'] : null;
                $row['sort_order'] = $index;
            }
            InternshipSubmissionFile::create($row);
            $index++;
        }
        $this->clearDrafts($assignment, $user);

        $assignment->attempt_count = $attempt;
        $assignment->status = 'submitted';
        $assignment->save();

        $this->log($assignment->enrolment_id, 'submission_received', null, [
            'submission_id' => $submission->id,
            'attempt' => $attempt,
        ]);

        $this->notifySubmission($assignment->enrolment->fresh(['student', 'program', 'supervisor']), $assignment->fresh(['task']), $submission);

        // No task is released here: the next one is scheduled when the supervisor
        // accepts this submission (see gradeSubmission).

        return $submission;
    }

    /**
     * Accept either a flat list of UploadedFile or evidence rows with a file
     * plus the short note the student wrote about that screenshot.
     *
     * @param  array  $uploadedFiles
     * @return array<int, array{file:UploadedFile,caption:string}>
     */
    protected function normalizeEvidenceItems(array $uploadedFiles)
    {
        $items = [];
        foreach ($uploadedFiles as $item) {
            if ($item instanceof UploadedFile) {
                $items[] = ['file' => $item, 'caption' => ''];

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $file = $item['file'] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $caption = trim((string) ($item['caption'] ?? ''));
            if (strlen($caption) > 400) {
                $caption = substr($caption, 0, 400);
            }
            $items[] = ['file' => $file, 'caption' => $caption];
        }

        return $items;
    }

    public function storeDraftFile(InternshipTaskAssignment $assignment, User $user, UploadedFile $file, $slotIndex, $caption = '')
    {
        if ((int) $assignment->enrolment->student_user_id !== (int) $user->id) {
            abort(403);
        }
        if (! in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true)) {
            throw new \RuntimeException('This task cannot accept files right now.');
        }
        if (! $file->isValid()) {
            throw new \RuntimeException('That file did not finish uploading. Try again.');
        }
        if ((int) $file->getSize() > 20 * 1024 * 1024) {
            throw new \RuntimeException('Each file must be 20 MB or smaller.');
        }

        $slotIndex = max(0, (int) $slotIndex);
        $existing = InternshipDraftFile::where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->where('slot_index', $slotIndex)
            ->get();
        foreach ($existing as $old) {
            $this->deleteDraftFile($old, $user);
        }

        $count = InternshipDraftFile::where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->count();
        if ($count >= 40) {
            throw new \RuntimeException('You already have 40 files waiting. Remove one before adding another.');
        }

        $path = $file->store('internship/drafts/'.$assignment->id, 'local');
        $row = InternshipDraftFile::create([
            'assignment_id' => $assignment->id,
            'student_user_id' => $user->id,
            'slot_index' => $slotIndex,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'checksum' => @hash_file('sha256', $file->getRealPath()) ?: null,
            'caption' => $caption !== '' ? substr($caption, 0, 400) : null,
        ]);

        if ($assignment->status === 'available') {
            try {
                $this->startAssignment($assignment, $user);
            } catch (\Throwable $e) {
            }
        }

        return $row;
    }

    public function deleteDraftFile(InternshipDraftFile $draft, User $user)
    {
        if ((int) $draft->student_user_id !== (int) $user->id) {
            abort(403);
        }
        try {
            Storage::disk($draft->disk ?: 'local')->delete($draft->path);
        } catch (\Throwable $e) {
        }
        $draft->delete();
    }

    public function updateDraftCaption(InternshipDraftFile $draft, User $user, $caption)
    {
        if ((int) $draft->student_user_id !== (int) $user->id) {
            abort(403);
        }
        $draft->caption = $caption !== '' ? substr(trim((string) $caption), 0, 400) : null;
        $draft->save();

        return $draft;
    }

    public function assignmentDrafts(InternshipTaskAssignment $assignment, User $user)
    {
        return InternshipDraftFile::where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->orderBy('slot_index')
            ->orderBy('id')
            ->get();
    }

    protected function copyDraftToSubmission(InternshipDraftFile $draft, InternshipSubmission $submission, $dir, $hasCaption, $index)
    {
        $disk = Storage::disk($draft->disk ?: 'local');
        $name = basename($draft->path);
        $dest = $dir.'/'.$name;
        if ($disk->exists($draft->path)) {
            $disk->copy($draft->path, $dest);
        } else {
            $dest = $draft->path;
        }
        $row = [
            'submission_id' => $submission->id,
            'disk' => $draft->disk ?: 'local',
            'path' => $dest,
            'original_name' => $draft->original_name,
            'mime' => $draft->mime,
            'size' => $draft->size ?: 0,
            'checksum' => $draft->checksum,
        ];
        if ($hasCaption) {
            $row['caption'] = $draft->caption;
            $row['sort_order'] = $index;
        }
        InternshipSubmissionFile::create($row);
    }

    protected function clearDrafts(InternshipTaskAssignment $assignment, User $user)
    {
        $drafts = InternshipDraftFile::where('assignment_id', $assignment->id)
            ->where('student_user_id', $user->id)
            ->get();
        foreach ($drafts as $draft) {
            try {
                Storage::disk($draft->disk ?: 'local')->delete($draft->path);
            } catch (\Throwable $e) {
            }
            $draft->delete();
        }
    }

    /**
     * Record a supervisor decision against the task's marking rubric.
     *
     * The stored decision always matches what actually happened to the task, so
     * a score below the pass mark can never be filed as an acceptance. When the
     * supervisor asks to accept below the pass mark they must say so explicitly
     * (`accept_below_pass`), which is recorded as a waiver.
     *
     * @throws \RuntimeException when the submission is not reviewable or the marks are inconsistent with the decision
     */
    public function gradeSubmission(InternshipSubmission $submission, User $grader, array $data)
    {
        $assignment = $submission->assignment;
        $enrolment = $assignment ? $assignment->enrolment : null;
        if (! $assignment || ! $enrolment) {
            throw new \RuntimeException('This submission is no longer linked to a task.');
        }
        if ($submission->status !== 'submitted' || $assignment->status !== 'submitted') {
            throw new \RuntimeException('This submission has already been reviewed. Reload the queue to see the current decision.');
        }

        $criteria = InternshipRubric::criteria($assignment->task);
        $marking = InternshipRubric::marks($criteria, (array) ($data['rubric_scores'] ?? []));
        if ($marking['errors']) {
            throw new \RuntimeException(implode(' ', $marking['errors']));
        }

        $score = $marking['total'] === null ? (int) ($data['score'] ?? 0) : $marking['total'];
        $score = max(0, min(100, $score));
        $passMark = (int) (optional($assignment->task)->pass_mark ?: 60);
        $wantsPass = ($data['decision'] ?? 'pass') !== 'revision_required';
        $waived = $wantsPass && $score < $passMark && ! empty($data['accept_below_pass']);

        if ($wantsPass && $score < $passMark && ! $waived) {
            throw new \RuntimeException(
                'Total score '.$score.'/100 is below the '.$passMark.' pass mark, so this cannot be accepted. '
                .'Raise the marks, choose "Request revision", or tick "Accept below the pass mark" to record a waiver.'
            );
        }

        $decision = $wantsPass ? 'pass' : 'revision_required';
        $feedback = trim((string) ($data['feedback'] ?? ''));
        if (! $wantsPass && $feedback === '') {
            throw new \RuntimeException('Write feedback saying what the student must fix before they resubmit.');
        }
        if ($waived) {
            $feedback = trim($feedback."\n\nAccepted below the pass mark ({$score}/100 against a pass mark of {$passMark}) by "
                .$grader->name.'.');
        }

        $grade = InternshipGrade::create([
            'submission_id' => $submission->id,
            'grader_id' => $grader->id,
            'score' => $score,
            'rubric_scores_json' => json_encode($marking['stored']),
            'feedback' => $feedback !== '' ? $feedback : null,
            'decision' => $decision,
            'graded_at' => now(),
        ]);

        if ($decision === 'pass') {
            $this->applyAcceptance($submission, $assignment, $enrolment, $grade);
        } else {
            DB::transaction(function () use ($submission, $assignment) {
                $submission->status = 'revision_required';
                $submission->save();
                $assignment->status = 'revision_required';
                $assignment->save();
            });
            $this->notifyRevision($enrolment->fresh(['student', 'program']), $assignment->fresh(['task']), $grade);
        }

        $this->log($enrolment->id, 'graded', null, [
            'submission_id' => $submission->id,
            'decision' => $decision,
            'score' => $grade->score,
            'rubric' => $marking['stored'] ?: null,
            'below_pass_waiver' => $waived,
        ]);

        return $grade;
    }

    /**
     * Mark an accepted submission passed, count progress, and release the next
     * curriculum task immediately so a late grade does not skip a day.
     * Shared by supervisor acceptance and the review-SLA auto-acceptance.
     */
    protected function applyAcceptance(
        InternshipSubmission $submission,
        InternshipTaskAssignment $assignment,
        InternshipEnrolment $enrolment,
        InternshipGrade $grade
    ) {
        DB::transaction(function () use ($submission, $assignment, $enrolment) {
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
            $enrolment->next_release_date = null;
            $enrolment->save();
        });

        $enrolment = $enrolment->fresh(['student', 'program']);
        $timesheetLabel = null;
        if ($enrolment->status === 'completed') {
            $this->notifyPassed($enrolment, $assignment->fresh(['task']), $grade, null);
            $this->notifyCompleted($enrolment);
        } else {
            try {
                $this->tryReleaseNext($enrolment, Carbon::today(), true, true);
            } catch (\Throwable $e) {
                Log::warning('Immediate next-task release after acceptance failed for enrolment '.$enrolment->id.': '.$e->getMessage());
            }
            $enrolment = $enrolment->fresh(['student', 'program']);
            $nextAssignment = $enrolment->currentOpenAssignment();
            if ($nextAssignment && $nextAssignment->scheduled_work_date) {
                try {
                    $timesheetLabel = Carbon::parse($nextAssignment->scheduled_work_date)->format('D d M Y');
                } catch (\Throwable $e) {
                    $timesheetLabel = (string) $nextAssignment->scheduled_work_date;
                }
            }
            $this->notifyPassed($enrolment, $assignment->fresh(['task']), $grade, $timesheetLabel);
            $this->nudgeWorkingWeekIfBlocking($enrolment->fresh(['student']));
        }

        return $enrolment->fresh();
    }

    /**
     * A student with no saved working week has no next working day, so the
     * accepted task is their last one until they set the timetable. Ask them,
     * at most once a day, instead of letting the placement stall in silence.
     */
    protected function nudgeWorkingWeekIfBlocking(InternshipEnrolment $enrolment)
    {
        $student = $enrolment->student ?: optional($enrolment->fresh(['student']))->student;
        if (! $student || $enrolment->next_release_date || InternCompliance::workingWeekConfigured($student)) {
            return;
        }

        $key = 'working_week_required:'.$enrolment->id.':'.Carbon::today()->toDateString();
        if ($this->alreadyNotified($key)) {
            return;
        }

        $msg = WhatsAppMessage::internshipWorkingWeekRequest(
            $student->name,
            url('/login'),
            url('/admin/timesheet/working-week')
        );
        $this->sendWhatsApp($student, $msg, $key, 'working_week_required');
    }

    public function reviewSlaWorkingDays()
    {
        return max(0, (int) config('internship.review_sla_working_days', 2));
    }

    public function reviewReminderWorkingDays()
    {
        return max(0, (int) config('internship.review_reminder_working_days', 1));
    }

    /**
     * Nth working day (in the intern's own timetable) after a submission, at that
     * day's end time. Null when auto-acceptance is switched off.
     *
     * @return \Carbon\Carbon|null
     */
    public function reviewDeadlineFor(InternshipSubmission $submission, $workingDays = null)
    {
        $workingDays = $workingDays === null ? $this->reviewSlaWorkingDays() : (int) $workingDays;
        if ($workingDays < 1) {
            return null;
        }

        $submittedAt = $submission->submitted_at
            ? Carbon::parse($submission->submitted_at)
            : Carbon::parse($submission->created_at ?: now());

        $student = optional($submission->assignment)->enrolment
            ? optional($submission->assignment->enrolment)->student
            : $submission->student;

        if (! $student || ! InternCompliance::workingWeekConfigured($student)) {
            // No timetable to count against — fall back to calendar days.
            return $submittedAt->copy()->addDays($workingDays)->endOfDay();
        }

        $cursor = $submittedAt->copy()->startOfDay();
        for ($i = 0; $i < $workingDays; $i++) {
            $next = $this->nextWorkingDate($student, $cursor->copy()->addDay());
            if (! $next) {
                return $submittedAt->copy()->addDays($workingDays)->endOfDay();
            }
            $cursor = $next;
        }

        return $this->endOfWorkingDay($student, $cursor);
    }

    /**
     * The intern's configured finish time on $date (17:00 when unset).
     */
    protected function endOfWorkingDay(User $user, Carbon $date)
    {
        $ww = WorkingWeek::where('user_id', $user->id)->first();
        $day = strtolower($date->format('l'));
        $end = $ww ? ($ww->{$day.'_end'} ?: '17:00') : '17:00';
        try {
            return Carbon::createFromFormat('Y-m-d H:i', $date->toDateString().' '.substr($end, 0, 5));
        } catch (\Throwable $e) {
            return $date->copy()->startOfDay()->setTime(17, 0, 0);
        }
    }

    /**
     * Submissions still waiting for a supervisor, with SLA context for the queue UI.
     *
     * @return array{deadline:?\Carbon\Carbon,overdue:bool,waiting_hours:int}
     */
    public function reviewSlaStatus(InternshipSubmission $submission)
    {
        $deadline = $this->reviewDeadlineFor($submission);
        $submittedAt = $submission->submitted_at
            ? Carbon::parse($submission->submitted_at)
            : Carbon::parse($submission->created_at ?: now());

        return [
            'deadline' => $deadline,
            'overdue' => $deadline ? now()->greaterThan($deadline) : false,
            'waiting_hours' => (int) $submittedAt->diffInHours(now()),
        ];
    }

    /**
     * Nudge supervisors about submissions that have waited past the reminder
     * threshold but are not yet auto-accepted. One nudge per submission.
     *
     * @return int reminders sent
     */
    public function remindPendingReviews()
    {
        $threshold = $this->reviewReminderWorkingDays();
        if ($threshold < 1) {
            return 0;
        }

        $sent = 0;
        $pending = InternshipSubmission::with([
            'student', 'assignment.task', 'assignment.enrolment.student', 'assignment.enrolment.program',
        ])->where('status', 'submitted')->orderBy('submitted_at')->get();

        foreach ($pending as $submission) {
            $assignment = $submission->assignment;
            $enrolment = $assignment ? $assignment->enrolment : null;
            if (! $assignment || ! $enrolment) {
                continue;
            }

            $remindAt = $this->reviewDeadlineFor($submission, $threshold);
            if (! $remindAt || now()->lessThan($remindAt)) {
                continue;
            }

            $deadline = $this->reviewDeadlineFor($submission);
            if ($deadline && now()->greaterThan($deadline)) {
                // Past the SLA already — auto-acceptance handles this one.
                continue;
            }

            $taskLabel = '#'.$assignment->progression_day.' — '.optional($assignment->task)->title;
            $url = url('/admin/internship/supervisor/submissions/'.$submission->id);

            foreach ($this->supervisorRecipients($enrolment) as $supervisor) {
                $key = 'review_reminder:'.$submission->id.':supervisor:'.$supervisor->id;
                if ($this->alreadyNotified($key)) {
                    continue;
                }
                $msg = WhatsAppMessage::internshipReviewReminder(
                    $supervisor->name,
                    optional($enrolment->student)->name,
                    $taskLabel,
                    optional($submission->submitted_at)->format('d M Y H:i'),
                    $deadline ? $deadline->format('D d M Y H:i') : null,
                    $url
                );
                $result = $this->sendWhatsApp($supervisor, $msg, $key, 'review_reminder');
                if (! empty($result['success'])) {
                    $sent++;
                }
                usleep(5500000);
            }
        }

        return $sent;
    }

    /**
     * Accept submissions no supervisor reviewed inside the SLA, so a silent
     * supervisor cannot stall a placement. Records the grade as auto-accepted.
     *
     * @return int submissions auto-accepted
     */
    public function autoAcceptOverdueSubmissions()
    {
        if ($this->reviewSlaWorkingDays() < 1) {
            return 0;
        }

        $accepted = 0;
        $pending = InternshipSubmission::with([
            'student', 'assignment.task', 'assignment.enrolment.student', 'assignment.enrolment.program',
        ])->where('status', 'submitted')->orderBy('submitted_at')->get();

        foreach ($pending as $submission) {
            $assignment = $submission->assignment;
            $enrolment = $assignment ? $assignment->enrolment : null;
            if (! $assignment || ! $enrolment || $assignment->status !== 'submitted') {
                continue;
            }

            $deadline = $this->reviewDeadlineFor($submission);
            if (! $deadline || now()->lessThanOrEqualTo($deadline)) {
                continue;
            }

            try {
                $this->autoAcceptSubmission($submission, $assignment, $enrolment, $deadline);
                $accepted++;
            } catch (\Throwable $e) {
                Log::warning('Internship auto-accept failed for submission '.$submission->id.': '.$e->getMessage());
            }
        }

        return $accepted;
    }

    protected function autoAcceptSubmission(
        InternshipSubmission $submission,
        InternshipTaskAssignment $assignment,
        InternshipEnrolment $enrolment,
        Carbon $deadline
    ) {
        $passMark = (int) (optional($assignment->task)->pass_mark ?: 60);
        $configured = config('internship.auto_accept_score');
        $score = $configured === null ? $passMark : max(0, min(100, (int) $configured));
        $slaDays = $this->reviewSlaWorkingDays();

        $grade = InternshipGrade::create([
            'submission_id' => $submission->id,
            'grader_id' => null,
            'score' => $score,
            'rubric_scores_json' => json_encode([]),
            'feedback' => 'Auto-accepted: no supervisor review within '.$slaDays
                .' working day'.($slaDays === 1 ? '' : 's').' of submission.',
            'decision' => 'pass',
            'graded_at' => now(),
            'auto_accepted' => true,
        ]);

        $this->applyAcceptance($submission, $assignment, $enrolment, $grade);

        $this->log($enrolment->id, 'auto_accepted', null, [
            'submission_id' => $submission->id,
            'deadline' => $deadline->toDateTimeString(),
            'score' => $grade->score,
        ]);

        $this->notifyAutoAcceptance($enrolment->fresh(['student', 'program']), $assignment->fresh(['task']), $submission, $grade);

        return $grade;
    }

    /**
     * Tell supervisors their review window lapsed (the student already learns of
     * the acceptance through the standard notifyPassed message).
     */
    protected function notifyAutoAcceptance(
        InternshipEnrolment $enrolment,
        InternshipTaskAssignment $assignment,
        InternshipSubmission $submission,
        InternshipGrade $grade
    ) {
        $taskLabel = '#'.$assignment->progression_day.' — '.optional($assignment->task)->title;
        $url = url('/admin/internship/supervisor/submissions/'.$submission->id);
        $slaDays = $this->reviewSlaWorkingDays();

        foreach ($this->supervisorRecipients($enrolment) as $supervisor) {
            $key = 'auto_accepted:'.$submission->id.':supervisor:'.$supervisor->id;
            if ($this->alreadyNotified($key)) {
                continue;
            }
            usleep(5500000);
            $msg = WhatsAppMessage::internshipReviewSlaBreached(
                $supervisor->name,
                optional($enrolment->student)->name,
                $taskLabel,
                $slaDays,
                'released now',
                $url
            );
            $this->sendWhatsApp($supervisor, $msg, $key, 'review_sla_breached');
        }
    }

    /**
     * @return \App\User[]
     */
    protected function supervisorRecipients(InternshipEnrolment $enrolment)
    {
        $ids = $enrolment->supervisorUserIds();
        if (empty($ids) && $enrolment->supervisor_id) {
            $ids = [(int) $enrolment->supervisor_id];
        }

        $out = [];
        foreach ($ids as $id) {
            $user = User::where('is_deleted', false)->find($id);
            if ($user) {
                $out[] = $user;
            }
        }

        return $out;
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
            $open->load(['task', 'latestSubmission.grades.grader', 'latestSubmission.files']);
        }

        $last = InternshipTaskAssignment::with(['task', 'latestSubmission.grades.grader'])
            ->where('enrolment_id', $enrolment->id)
            ->where('status', 'passed')
            ->orderByDesc('progression_day')
            ->first();

        return [
            'enrolment' => $enrolment,
            'assignment' => $open,
            'last_passed' => $last,
        ];
    }

    /**
     * Supervisors the intern should see (primary + extra refs), with contact details.
     *
     * @return array<int, array{name:string,email:string,phone:string,source:string}>
     */
    public function studentSupervisors(InternshipEnrolment $enrolment)
    {
        $people = [];
        $seen = [];
        $push = function ($name, $email, $phone, $source) use (&$people, &$seen) {
            $name = trim((string) $name);
            $email = trim((string) $email);
            $phone = trim((string) $phone);
            if ($name === '' && $email === '' && $phone === '') {
                return;
            }
            $key = $email !== ''
                ? 'e:'.strtolower($email)
                : ($phone !== '' ? 'p:'.preg_replace('/\D+/', '', $phone) : 'n:'.strtolower($name));
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $people[] = [
                'name' => $name !== '' ? $name : 'Supervisor',
                'email' => $email,
                'phone' => $phone,
                'source' => $source,
            ];
        };

        $primary = $enrolment->relationLoaded('supervisor')
            ? $enrolment->supervisor
            : ($enrolment->supervisor_id
                ? User::where('is_deleted', false)->find($enrolment->supervisor_id)
                : null);
        if ($primary) {
            $push(
                $primary->name,
                $primary->email,
                $primary->phone ?: ($primary->additional_phone ?? ''),
                'Primary supervisor'
            );
        }

        foreach ($enrolment->supervisorRefs() as $ref) {
            if (strpos($ref, 'user:') === 0) {
                $user = User::where('is_deleted', false)->find((int) substr($ref, 5));
                if ($user) {
                    $push(
                        $user->name,
                        $user->email,
                        $user->phone ?: ($user->additional_phone ?? ''),
                        'Supervisor'
                    );
                }
            } elseif (strpos($ref, 'customer:') === 0) {
                $customer = \App\Customer::find((int) substr($ref, 9));
                if ($customer) {
                    $push(
                        $customer->name ?: $customer->company_name,
                        $customer->email,
                        $customer->phone_number,
                        'Supervisor'
                    );
                }
            }
        }

        return $people;
    }

    /**
     * Whether the intern may pull the next curriculum task themselves.
     * They may request only the task they should have received and do not already hold.
     *
     * @return array{can_request:bool,reason:string,message:string,next_day:?int,task_title:?string}
     */
    public function studentTaskRequestState(InternshipEnrolment $enrolment, User $user)
    {
        $empty = [
            'can_request' => false,
            'reason' => 'none',
            'message' => '',
            'next_day' => null,
            'task_title' => null,
        ];

        if (! $enrolment || (int) $enrolment->student_user_id !== (int) $user->id) {
            $empty['reason'] = 'not_yours';
            $empty['message'] = 'This placement is not yours.';

            return $empty;
        }

        if ($enrolment->status !== 'active') {
            $empty['reason'] = 'not_active';
            $empty['message'] = $enrolment->status === 'completed'
                ? 'Your internship is complete.'
                : 'Your placement is paused. Ask your supervisor to resume it.';

            return $empty;
        }

        $open = $enrolment->currentOpenAssignment();
        if ($open) {
            $empty['reason'] = 'already_has_task';
            $empty['message'] = 'You already have Task #'.$open->progression_day
                .' — '.(optional($open->task)->title ?: 'your current task').'. Open it to continue.';

            return $empty;
        }

        if ($enrolment->releaseHeldUntil()) {
            $empty['reason'] = 'held';
            $empty['message'] = 'Your supervisor accepted your last submission. The next task should appear shortly. Refresh this page if it is not here yet.';

            return $empty;
        }

        $nextDay = $enrolment->nextCurriculumDay();
        if (! $nextDay) {
            $empty['reason'] = 'complete';
            $empty['message'] = 'There is no further task in your placement.';

            return $empty;
        }

        $task = InternshipProgramTask::where('program_id', $enrolment->program_id)
            ->where('day_number', $nextDay)
            ->where('is_active', true)
            ->first();
        $title = $task ? $task->title : null;

        $releasedCount = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereNotNull('released_at')
            ->count();
        $isFirstTask = $releasedCount === 0;

        if (! $isFirstTask && ! InternCompliance::workingWeekConfigured($user)) {
            $empty['reason'] = 'no_week';
            $empty['message'] = 'Set your Working Week first, then you can request today’s task.';
            $empty['next_day'] = $nextDay;
            $empty['task_title'] = $title;

            return $empty;
        }

        if (! $isFirstTask && ! $this->isWorkingDate($user, Carbon::today())) {
            $empty['reason'] = 'not_working_day';
            $empty['message'] = 'Today is not one of your working days, so today’s task cannot be requested yet.';
            $empty['next_day'] = $nextDay;
            $empty['task_title'] = $title;

            return $empty;
        }

        if ($task) {
            $existing = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->where('program_task_id', $task->id)
                ->first();
            if ($existing && in_array($existing->status, ['passed', 'skipped', 'available', 'in_progress', 'submitted', 'revision_required'], true)) {
                $empty['reason'] = 'already_received';
                $empty['message'] = 'You have already received Task #'.$nextDay.'.';
                $empty['next_day'] = $nextDay;
                $empty['task_title'] = $title;

                return $empty;
            }
        }

        return [
            'can_request' => true,
            'reason' => 'ready',
            'message' => $isFirstTask
                ? 'Your first task has not been released yet. Request it to start.'
                : 'You have not received today’s task yet. Request it now.',
            'next_day' => $nextDay,
            'task_title' => $title,
        ];
    }

    /**
     * Intern pulls the next curriculum task they should have received (not a different one).
     *
     * @return InternshipTaskAssignment
     */
    public function requestNextTaskForStudent(InternshipEnrolment $enrolment, User $user)
    {
        if ((int) $enrolment->student_user_id !== (int) $user->id) {
            abort(403);
        }

        $state = $this->studentTaskRequestState($enrolment, $user);
        if (empty($state['can_request'])) {
            throw new \RuntimeException($state['message'] ?: 'You cannot request a task right now.');
        }

        $ok = $this->tryReleaseNext($enrolment, Carbon::today(), true);
        if (! $ok) {
            throw new \RuntimeException('Could not release your next task. Try again shortly or ask your supervisor.');
        }

        $enrolment->refresh();
        $assignment = $enrolment->currentOpenAssignment();
        if (! $assignment) {
            throw new \RuntimeException('The task was released but could not be loaded. Refresh this page.');
        }

        return $assignment;
    }

    /**
     * Latest grade + review SLA for the intern’s current or last assignment.
     *
     * @return array{status:string,label:string,score:?int,decision:?string,feedback:?string,grader:?string,auto_accepted:bool,deadline:?\Carbon\Carbon,waiting_hours:?int}
     */
    public function studentGradeSummary(InternshipTaskAssignment $assignment = null)
    {
        $empty = [
            'status' => 'none',
            'label' => 'No submission yet',
            'score' => null,
            'decision' => null,
            'feedback' => null,
            'grader' => null,
            'auto_accepted' => false,
            'deadline' => null,
            'waiting_hours' => null,
            'pass_mark' => null,
            'rubric' => ['rows' => [], 'earned' => null, 'possible' => null, 'percent' => null],
        ];
        if (! $assignment) {
            return $empty;
        }

        $assignment->loadMissing(['latestSubmission.grades.grader', 'task']);
        $submission = $assignment->latestSubmission;
        $grade = $submission ? $submission->grades->first() : null;

        if ($assignment->status === 'available' || $assignment->status === 'in_progress') {
            return array_merge($empty, [
                'status' => $assignment->status,
                'label' => $assignment->status === 'available'
                    ? 'Ready — not submitted yet'
                    : 'In progress — not submitted yet',
            ]);
        }

        if ($assignment->status === 'submitted') {
            $sla = $submission ? $this->reviewSlaStatus($submission) : ['deadline' => null, 'waiting_hours' => null];

            return array_merge($empty, [
                'status' => 'submitted',
                'label' => 'Waiting for supervisor review',
                'deadline' => $sla['deadline'] ?? null,
                'waiting_hours' => $sla['waiting_hours'] ?? null,
            ]);
        }

        if ($grade) {
            // The task status is the source of truth: a legacy grade can carry
            // decision 'pass' on a task that was actually sent back for revision.
            $passed = $assignment->status === 'passed';

            return array_merge($empty, [
                'status' => $passed ? 'passed' : 'revision_required',
                'label' => $passed
                    ? ($grade->auto_accepted ? 'Accepted (auto-accepted)' : 'Accepted by supervisor')
                    : 'Revision required',
                'score' => $grade->score,
                'decision' => $passed ? 'pass' : 'revision_required',
                'feedback' => $grade->feedback,
                'grader' => optional($grade->grader)->name,
                'auto_accepted' => (bool) $grade->auto_accepted,
                'pass_mark' => (int) (optional($assignment->task)->pass_mark ?: 60),
                'rubric' => InternshipRubric::breakdown($grade, $assignment->task),
            ]);
        }

        if ($assignment->status === 'revision_required') {
            return array_merge($empty, [
                'status' => 'revision_required',
                'label' => 'Revision required',
            ]);
        }

        if ($assignment->status === 'passed') {
            return array_merge($empty, [
                'status' => 'passed',
                'label' => 'Accepted',
            ]);
        }

        return array_merge($empty, [
            'status' => $assignment->status,
            'label' => ucfirst(str_replace('_', ' ', $assignment->status)),
        ]);
    }

    /**
     * WhatsApp the intern a login + Working Week link they can open immediately.
     *
     * @return array{success:bool,error?:string,url:string}
     */
    public function requestWorkingWeekSetup(User $user = null, $name = null, $phone = null)
    {
        $weekUrl = url('/admin/timesheet/working-week');
        $loginUrl = url('/login');
        $displayName = $name ?: ($user ? $user->name : 'Intern');
        $msg = WhatsAppMessage::internshipWorkingWeekRequest($displayName, $loginUrl, $weekUrl);

        if ($user) {
            $key = 'ww_request:'.$user->id.':'.now()->format('YmdHis');
            $result = $this->sendWhatsApp($user, $msg, $key, 'working_week_request');
            $result['url'] = $weekUrl;

            return $result;
        }

        $phone = trim((string) $phone);
        if ($phone === '') {
            return ['success' => false, 'error' => 'No intern user or phone number to message.', 'url' => $weekUrl];
        }

        try {
            $result = app(NotificationRouter::class)->sendWhatsAppText($phone, $msg);
            $result['url'] = $weekUrl;

            return $result;
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'url' => $weekUrl];
        }
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

        $workDateLabel = (string) $assignment->scheduled_work_date;
        $availableNow = false;
        try {
            if ($assignment->scheduled_work_date
                && Carbon::parse($assignment->scheduled_work_date)->startOfDay()->greaterThan(Carbon::today())) {
                $availableNow = true;
                $workDateLabel = Carbon::parse($assignment->scheduled_work_date)->format('D d M Y');
            }
        } catch (\Throwable $e) {
        }

        $msg = WhatsAppMessage::internshipDailyTask(
            $student->name,
            optional($program)->displayName() ?? '',
            $taskLabel,
            $workDateLabel,
            $url,
            $task->instructions(),
            $hasHandbook,
            $availableNow
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
        $msg .= WhatsAppMessage::bullet('Score', $grade->score.'/100');
        $msg .= InternshipRubric::whatsAppBreakdown($grade, $assignment->task);
        if ($grade->feedback) {
            $msg .= "\n*Feedback:*\n".$grade->feedback."\n";
        }
        $msg .= WhatsAppMessage::actionLink('See results and resubmit', $url);
        $msg .= WhatsAppMessage::footer();
        $this->sendWhatsApp($student, $msg, $key, 'revision_requested');
    }

    protected function notifyPassed(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, InternshipGrade $grade, $timesheetDateLabel = null)
    {
        $student = $enrolment->student;
        if (! $student) {
            return;
        }
        $key = 'passed:'.$grade->id.':student:'.$student->id;
        if ($this->alreadyNotified($key)) {
            return;
        }
        $msg = WhatsAppMessage::statusBlock('✅', 'Submission Grades');
        $msg .= WhatsAppMessage::greeting($student->name);
        $msg .= $grade->auto_accepted
            ? "Your task was accepted so your placement keeps moving — your supervisor may still send feedback.\n\n"
            : "Great work — your supervisor accepted your internship task.\n\n";
        $msg .= WhatsAppMessage::bullet('Task', '#'.$assignment->progression_day.' — '.optional($assignment->task)->title);
        $msg .= WhatsAppMessage::bullet('Score', $grade->score.'/100');
        $msg .= InternshipRubric::whatsAppBreakdown($grade, $assignment->task);
        $msg .= WhatsAppMessage::bullet('Progress', $enrolment->completed_count.'/'.$enrolment->plannedDurationDays());
        if ($enrolment->completed_count < $enrolment->plannedDurationDays()) {
            $msg .= WhatsAppMessage::bullet('Next task', 'Available now');
            if ($timesheetDateLabel) {
                $msg .= WhatsAppMessage::bullet('Timesheet', $timesheetDateLabel);
                $msg .= "\nYour next task is ready now. Log hours on that working day, at your start time.";
            } else {
                $msg .= "\nYour next task is ready now. Timesheets are only due on your working days.";
            }
        }
        if ($grade->feedback) {
            $msg .= "\n*Feedback:*\n".$grade->feedback."\n";
        }
        $msg .= WhatsAppMessage::actionLink('See results in your portal', url('/admin/internship/student/task/'.$assignment->id));
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
     * Take ownership of a notification key before sending.
     *
     * The key is unique, so the insert doubles as the duplicate guard. A row
     * that was never delivered (failed, skipped because WhatsApp was switched
     * off, or abandoned mid-flight) is reclaimed so the message can still go
     * out — otherwise one bad attempt would suppress it forever.
     *
     * @return bool false when the message was already delivered or another send is in flight
     */
    protected function claimNotification(array $row)
    {
        $key = $row['idempotency_key'];
        try {
            DB::table('internship_notification_logs')->insert($row);

            return true;
        } catch (\Throwable $e) {
            $existing = DB::table('internship_notification_logs')->where('idempotency_key', $key)->first();
            if (! $existing || $existing->status === 'sent') {
                return false;
            }
            // Give up eventually so a permanently bad number is not retried every hour.
            if ($existing->status === 'failed' && (int) $existing->attempts >= self::MAX_NOTIFICATION_ATTEMPTS) {
                return false;
            }
            // Leave a recent 'pending' row alone: a concurrent run may still be sending it.
            if ($existing->status === 'pending'
                && $existing->updated_at
                && Carbon::parse($existing->updated_at)->greaterThan(now()->subMinutes(15))) {
                return false;
            }

            DB::table('internship_notification_logs')->where('idempotency_key', $key)->update([
                'event' => $row['event'],
                'user_id' => $row['user_id'],
                'channel' => $row['channel'],
                'phone' => $row['phone'],
                'status' => 'pending',
                'attempts' => ((int) $existing->attempts) + 1,
                'error' => null,
                'updated_at' => now(),
            ]);

            return true;
        }
    }

    /**
     * 'skipped' (WhatsApp switched off) stays retryable; only a real delivery is 'sent'.
     */
    protected function notificationOutcome(array $result)
    {
        if (! empty($result['skipped'])) {
            return 'skipped';
        }

        return ! empty($result['success']) ? 'sent' : 'failed';
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
        if (! $this->claimNotification($row)) {
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
                'status' => $this->notificationOutcome($result),
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
        if (! $phone) {
            $phone = $this->resolveFallbackPhoneForUser($user);
        }
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
        if (! $this->claimNotification($row)) {
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
                'status' => $this->notificationOutcome($result),
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

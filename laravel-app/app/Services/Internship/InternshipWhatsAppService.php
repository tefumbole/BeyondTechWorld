<?php

namespace App\Services\Internship;

use App\InternshipEnrolment;
use App\InternshipProgram;
use App\InternshipSubmission;
use App\InternshipTaskAssignment;
use App\Support\InternshipHandbook;
use App\Support\InternshipSubmissionFileGuard;
use App\User;
use App\WhatsApp\InternshipActivity;
use App\WhatsApp\InternshipIntake;
use App\WhatsApp\InternshipIntakeFile;
use App\WhatsApp\WhatsAppConversation;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InternshipWhatsAppService
{
    public function summary(array $context, array $params = [])
    {
        $resolved = $this->resolve($context, $params);
        if (! empty($resolved['needs_choice']) || empty($resolved['success'])) {
            return $resolved;
        }
        $enrolment = $resolved['enrolment'];
        $program = $this->programName($enrolment);
        $this->audit($context, null, 'summary', $program);

        return [
            'success' => true,
            'enrolment_id' => $enrolment->id,
            'program' => $program,
            'status' => $enrolment->status,
            'supervisor' => $this->supervisorName($enrolment),
        ];
    }

    public function currentTask(array $context, array $params = [])
    {
        $resolved = $this->resolve($context, $params);
        if (! empty($resolved['needs_choice']) || empty($resolved['success'])) {
            return $resolved;
        }
        $enrolment = $resolved['enrolment'];
        $text = isset($params['text']) ? strtolower((string) $params['text']) : '';
        $day = $this->requestedDay($text, $params);
        $tomorrow = ! empty($params['tomorrow']) || strpos($text, 'tomorrow') !== false;

        if ($tomorrow) {
            $next = method_exists($enrolment, 'nextCurriculumDay') ? $enrolment->nextCurriculumDay() : null;
            $row = $next ? $this->assignmentForDay($enrolment, $next) : null;
            if (! $row || ! $this->isReleased($row)) {
                $this->audit($context, null, 'task_locked', 'tomorrow');

                return ['success' => true, 'locked' => true];
            }

            return $this->taskPayload($enrolment, $row, $context, true);
        }

        if ($day) {
            $row = $this->assignmentForDay($enrolment, $day);
            if (! $row || ! $this->isReleased($row)) {
                $this->audit($context, null, 'task_locked', 'day '.$day);

                return ['success' => true, 'locked' => true, 'day' => $day];
            }

            return $this->taskPayload($enrolment, $row, $context, true);
        }

        $row = $this->currentAssignment($enrolment);
        if (! $row) {
            if ($this->hasUnreleasedAssignment($enrolment)) {
                $this->audit($context, null, 'task_locked', 'unreleased');

                return ['success' => true, 'locked' => true];
            }

            return ['success' => false, 'error' => 'no_task'];
        }
        if (! $this->isReleased($row)) {
            $this->audit($context, null, 'task_locked', 'current');

            return ['success' => true, 'locked' => true];
        }

        return $this->taskPayload($enrolment, $row, $context, true);
    }

    public function progress(array $context, array $params = [])
    {
        $resolved = $this->resolve($context, $params);
        if (! empty($resolved['needs_choice']) || empty($resolved['success'])) {
            return $resolved;
        }
        $enrolment = $resolved['enrolment'];
        $planned = method_exists($enrolment, 'plannedDurationDays') ? $enrolment->plannedDurationDays() : 0;
        $completed = (int) $enrolment->completed_count;
        if ($completed === 0 && Schema::hasTable('internship_task_assignments') && Schema::hasColumn('internship_task_assignments', 'status')) {
            $completed = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->where('status', 'passed')->count();
            if ($completed === 0) {
                $completed = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->where('status', 'completed')->count();
            }
        }
        $current = $this->currentAssignment($enrolment);
        $this->audit($context, null, 'progress', (string) $completed);

        return [
            'success' => true,
            'completed' => $completed,
            'planned' => $planned,
            'remaining' => $planned > 0 ? max(0, $planned - $completed) : null,
            'day' => $current && $current->progression_day ? (int) $current->progression_day : null,
            'status' => $enrolment->status,
            'program' => $this->programName($enrolment),
            'released' => Schema::hasTable('internship_task_assignments')
                ? InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->count()
                : 0,
        ];
    }

    public function materials(array $context, array $params = [])
    {
        $task = $this->currentTask($context, $params);
        if (empty($task['success']) || ! empty($task['locked'])) {
            return $task;
        }
        $assignment = InternshipTaskAssignment::with(['task', 'enrolment.program'])->find($task['assignment_id']);
        $path = null;
        $name = null;
        if ($assignment && $assignment->task && $assignment->enrolment && $assignment->enrolment->program) {
            $path = InternshipHandbook::absolutePath($assignment->enrolment->program, $assignment->task);
            $name = InternshipHandbook::downloadName($assignment->enrolment->program, $assignment->task);
        }
        $this->audit($context, null, 'material_request', $name ?: 'instructions');
        $task['document_path'] = ($path && is_file($path)) ? $path : null;
        $task['document_name'] = $name;
        if (empty($task['document_path']) && empty($task['instructions'])) {
            $task['error'] = 'no_material';
        }

        return $task;
    }

    public function submissionStatus(array $context, array $params = [])
    {
        $resolved = $this->resolve($context, $params);
        if (! empty($resolved['needs_choice']) || empty($resolved['success'])) {
            return $resolved;
        }
        $assignment = $this->currentAssignment($resolved['enrolment']);
        if (! $assignment || ! Schema::hasTable('internship_submissions')) {
            return ['success' => true, 'submission_status' => 'none'];
        }
        $submission = $assignment->latestSubmission;
        if (! $submission) {
            return ['success' => true, 'submission_status' => 'none', 'task_status' => $assignment->status];
        }
        $grade = Schema::hasTable('internship_grades') ? $submission->latestGrade : null;
        $this->audit($context, null, 'submission_status', (string) $submission->status);

        return [
            'success' => true,
            'submission_id' => $submission->id,
            'submission_status' => $submission->status,
            'task_status' => $assignment->status,
            'score' => $grade ? $grade->score : null,
            'decision' => $grade ? $grade->decision : null,
            'feedback' => $grade ? $grade->feedback : null,
        ];
    }

    public function prepare(array $context, array $params = [])
    {
        return $this->collect($context, $params, false);
    }

    public function confirm(array $context, array $params = [])
    {
        $params['confirm'] = true;

        return $this->collect($context, $params, true);
    }

    public function collect(array $context, array $params, $forceConfirm)
    {
        $resolved = $this->resolve($context, $params);
        if (! empty($resolved['needs_choice']) || empty($resolved['success'])) {
            return $resolved;
        }
        $enrolment = $resolved['enrolment'];
        $user = $resolved['user'];
        $eligible = $this->eligibleAssignments($enrolment);
        if ($eligible->count() === 0) {
            $previous = InternshipIntake::where('conversation_id', isset($context['conversation_id']) ? $context['conversation_id'] : 0)
                ->where('enrolment_id', $enrolment->id)
                ->where('status', InternshipIntake::SUBMITTED)
                ->whereNotNull('submission_id')
                ->orderByDesc('id')
                ->first();
            if ($previous) {
                $this->audit($context, $previous->id, 'duplicate_prevented', (string) $previous->submission_id);

                return [
                    'success' => true,
                    'duplicate' => true,
                    'submission_id' => $previous->submission_id,
                    'skip_assistant_reply' => false,
                ];
            }

            return ['success' => false, 'error' => 'no_task'];
        }
        $choice = $this->choiceIndex($params);
        if ($eligible->count() > 1 && $choice === null && empty($params['assignment_id'])) {
            $options = [];
            foreach ($eligible as $row) {
                $options[] = [
                    'assignment_id' => $row->id,
                    'day' => $row->progression_day,
                    'title' => $this->taskTitle($row),
                ];
            }
            $this->audit($context, null, 'task_ambiguous', (string) count($options));

            return ['success' => true, 'needs_choice' => true, 'choices' => $options];
        }
        $assignment = null;
        if (! empty($params['assignment_id'])) {
            $assignment = $eligible->firstWhere('id', (int) $params['assignment_id']);
        } elseif ($choice !== null) {
            $assignment = $eligible->values()->get($choice);
        } else {
            $assignment = $eligible->first();
        }
        if (! $assignment || (int) $assignment->enrolment_id !== (int) $enrolment->id) {
            return ['success' => false, 'error' => 'not_owner'];
        }
        if (! $this->isReleased($assignment)) {
            return ['success' => true, 'locked' => true];
        }

        $intake = $this->openIntake($context, $user, $enrolment, $assignment);
        $text = isset($params['text']) ? trim((string) $params['text']) : '';
        if ($text !== '') {
            $intake->text_body = trim($intake->text_body."\n".$text);
            $this->captureLinks($intake, $text);
        }
        if (! empty($params['url'])) {
            $this->captureLinks($intake, (string) $params['url']);
        }
        if (! empty($params['local_path'])) {
            $stored = $this->storeLocalFile($intake, $params, $assignment);
            if (empty($stored['success'])) {
                $intake->save();

                return $stored;
            }
        } elseif (! empty($params['media']) && is_array($params['media'])) {
            $queued = $this->queueMedia($intake, $params['media'], isset($params['provider_message_id']) ? $params['provider_message_id'] : null);
            if (empty($queued['success'])) {
                $intake->save();

                return $queued;
            }
        }
        $intake->save();
        $this->rememberProvider($intake, isset($params['provider_message_id']) ? $params['provider_message_id'] : null);
        $intake->save();

        if ($this->mediaStillPending($intake)) {
            return [
                'success' => true,
                'media_pending' => true,
                'assignment_id' => $assignment->id,
                'title' => $this->taskTitle($assignment),
            ];
        }

        $confirm = $forceConfirm || $this->isConfirmation($text, $intake);
        if (! $confirm) {
            $intake->status = InternshipIntake::AWAITING;
            $intake->validation_state = 'awaiting_confirm';
            $intake->save();
            $this->audit($context, $intake->id, 'intake_awaiting', $this->taskTitle($assignment));

            return $this->intakeSummary($intake, $assignment, $enrolment, false);
        }

        return $this->finalize($intake, $assignment, $enrolment, $user, $context);
    }

    public function handover(array $context, array $params = [])
    {
        $resolved = $this->resolve($context, $params);
        $conversation = isset($context['conversation']) ? $context['conversation'] : null;
        if ($conversation) {
            app(\App\Services\Assistant\AssistantHandoverService::class)->toHuman($conversation, isset($params['reason']) ? $params['reason'] : 'intern_request');
            if (! empty($resolved['success'])) {
                $ids = $resolved['enrolment']->supervisorUserIds();
                if (! empty($ids[0]) && Schema::hasColumn('whatsapp_conversations', 'assigned_user_id')) {
                    $conversation->assigned_user_id = $ids[0];
                    $conversation->save();
                }
            }
        }
        $this->audit($context, null, 'handover', isset($params['reason']) ? $params['reason'] : 'intern_request');

        return ['success' => true, 'handed_over' => true];
    }

    public function panel(WhatsAppConversation $conversation)
    {
        $context = app(\App\Services\Assistant\AssistantContextBuilder::class)->build($conversation);
        $resolved = $this->resolve($context, []);
        if (! empty($resolved['needs_choice']) || empty($resolved['success']) || empty($resolved['enrolment'])) {
            return null;
        }
        $enrolment = $resolved['enrolment'];
        $assignment = $this->currentAssignment($enrolment);
        $submission = ($assignment && Schema::hasTable('internship_submissions')) ? $assignment->latestSubmission : null;
        $progress = $this->progress($context, []);

        return [
            'name' => $resolved['user'] ? $resolved['user']->name : null,
            'program' => $this->programName($enrolment),
            'enrolment_status' => $enrolment->status,
            'enrolment_id' => $enrolment->id,
            'day' => $assignment ? $assignment->progression_day : null,
            'task' => $assignment ? $this->taskTitle($assignment) : null,
            'task_status' => $assignment ? $assignment->status : null,
            'assignment_id' => $assignment ? $assignment->id : null,
            'submission_id' => $submission ? $submission->id : null,
            'submission_status' => $submission ? $submission->status : null,
            'supervisor' => $this->supervisorName($enrolment),
            'completed' => isset($progress['completed']) ? $progress['completed'] : null,
            'planned' => isset($progress['planned']) ? $progress['planned'] : null,
        ];
    }

    public function metrics()
    {
        if (! Schema::hasTable('whatsapp_internship_intakes')) {
            return [
                'submissions_today' => 0,
                'awaiting_review' => 0,
                'corrections' => 0,
                'media_failures' => 0,
                'unresolved' => 0,
                'handovers' => 0,
            ];
        }
        $today = [Carbon::today()->startOfDay(), Carbon::now()->endOfDay()];

        return [
            'submissions_today' => InternshipIntake::where('status', InternshipIntake::SUBMITTED)->whereBetween('confirmed_at', $today)->count(),
            'awaiting_review' => Schema::hasTable('internship_submissions')
                ? InternshipSubmission::where('status', 'submitted')->count()
                : 0,
            'corrections' => Schema::hasTable('internship_task_assignments')
                ? InternshipTaskAssignment::where('status', 'revision_required')->count()
                : 0,
            'media_failures' => Schema::hasTable('whatsapp_internship_intake_files')
                ? InternshipIntakeFile::where('status', 'failed')->count()
                : 0,
            'unresolved' => InternshipIntake::whereNull('assignment_id')->where('status', InternshipIntake::COLLECTING)->count(),
            'handovers' => Schema::hasTable('whatsapp_internship_activities')
                ? InternshipActivity::where('type', 'handover')->count()
                : 0,
        ];
    }

    protected function finalize(InternshipIntake $intake, InternshipTaskAssignment $assignment, InternshipEnrolment $enrolment, User $user, array $context)
    {
        if ($intake->submission_id) {
            $existing = InternshipSubmission::find($intake->submission_id);
            $this->audit($context, $intake->id, 'duplicate_prevented', (string) $intake->submission_id);

            return [
                'success' => true,
                'duplicate' => true,
                'submission_id' => $intake->submission_id,
                'reference' => $existing ? ('attempt '.$existing->attempt_no) : null,
                'skip_assistant_reply' => false,
            ];
        }
        $missing = $this->missingRequirements($assignment, $intake);
        if ($missing) {
            $intake->validation_state = 'missing';
            $intake->save();
            $this->audit($context, $intake->id, 'validation_failed', $missing);

            return ['success' => false, 'error' => 'missing_requirement', 'missing' => $missing, 'title' => $this->taskTitle($assignment)];
        }

        $files = [];
        foreach ($intake->files()->where('status', 'stored')->where('kind', '!=', 'link')->get() as $file) {
            $full = storage_path('app/'.$file->path);
            if (! is_file($full)) {
                continue;
            }
            $files[] = new UploadedFile($full, $file->original_name ?: 'upload.bin', $file->mime, null, true);
        }
        foreach ($intake->links() as $url) {
            $tmp = storage_path('app/internship/whatsapp-intake/'.$intake->id.'/github-link-'.substr(sha1($url), 0, 8).'.txt');
            if (! is_dir(dirname($tmp))) {
                mkdir(dirname($tmp), 0755, true);
            }
            file_put_contents($tmp, $url."\n");
            $files[] = new UploadedFile($tmp, 'github-link.txt', 'text/plain', null, true);
        }
        if ($files === [] && trim((string) $intake->text_body) !== '' && $this->allowsTextOnly($assignment)) {
            $tmp = storage_path('app/internship/whatsapp-intake/'.$intake->id.'/text-submission.txt');
            if (! is_dir(dirname($tmp))) {
                mkdir(dirname($tmp), 0755, true);
            }
            file_put_contents($tmp, $intake->text_body);
            $files[] = new UploadedFile($tmp, 'text-submission.txt', 'text/plain', null, true);
        }
        if ($files === []) {
            return ['success' => false, 'error' => 'missing_requirement', 'missing' => 'file'];
        }

        $description = trim((string) $intake->text_body);
        if ($intake->links()) {
            $description = trim($description."\n".implode("\n", $intake->links()));
        }
        $description = trim("Source: WhatsApp\nConversation: ".$intake->conversation_id."\n".$description);

        try {
            $submission = app(InternshipProgramService::class)->submitAssignment($assignment, $user, $description, $files);
        } catch (\Throwable $e) {
            $intake->status = InternshipIntake::FAILED;
            $intake->error = $e->getMessage();
            $intake->save();
            $this->audit($context, $intake->id, 'submit_failed', $e->getMessage());

            return ['success' => false, 'error' => 'submit_failed', 'message' => $e->getMessage()];
        }

        $this->stampSource($submission, $intake);
        $intake->submission_id = $submission->id;
        $intake->status = InternshipIntake::SUBMITTED;
        $intake->confirmed_at = now();
        $intake->validation_state = 'submitted';
        $intake->save();
        $this->audit($context, $intake->id, 'official_submission', (string) $submission->id);

        $notified = $this->studentAlreadyNotified($submission, $user);

        return [
            'success' => true,
            'submission_id' => $submission->id,
            'attempt' => $submission->attempt_no,
            'title' => $this->taskTitle($assignment),
            'day' => $assignment->progression_day,
            'skip_assistant_reply' => $notified,
            'notified' => $notified,
        ];
    }

    protected function resolve(array $context, array $params)
    {
        $userId = isset($context['intern_user_id']) ? (int) $context['intern_user_id'] : 0;
        if (! $userId || ! Schema::hasTable('internship_enrolments')) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $query = InternshipEnrolment::where('student_user_id', $userId);
        if (Schema::hasColumn('internship_enrolments', 'status')) {
            $query->where('status', 'active');
        }
        $rows = $query->orderBy('id')->get();
        if ($rows->count() === 0) {
            return ['success' => false, 'error' => 'no_active_internship'];
        }
        $enrolment = null;
        if (! empty($params['enrolment_id'])) {
            $enrolment = $rows->firstWhere('id', (int) $params['enrolment_id']);
        } elseif ($rows->count() === 1) {
            $enrolment = $rows->first();
        } else {
            $choice = $this->choiceIndex($params);
            if ($choice !== null) {
                $enrolment = $rows->values()->get($choice);
            }
        }
        if (! $enrolment && $rows->count() > 1) {
            $options = [];
            foreach ($rows as $row) {
                $options[] = ['enrolment_id' => $row->id, 'program' => $this->programName($row), 'status' => $row->status];
            }

            return ['success' => true, 'needs_choice' => true, 'choices' => $options, 'error' => null];
        }
        if (! $enrolment) {
            return ['success' => false, 'error' => 'not_owner'];
        }

        return ['success' => true, 'enrolment' => $enrolment, 'user' => User::find($userId)];
    }

    protected function currentAssignment(InternshipEnrolment $enrolment)
    {
        if (! Schema::hasTable('internship_task_assignments')) {
            return null;
        }
        $query = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required', 'released']);
        if (Schema::hasColumn('internship_task_assignments', 'released_at')) {
            $query->whereNotNull('released_at');
        }

        return $query->orderByDesc('id')->first();
    }

    protected function hasUnreleasedAssignment(InternshipEnrolment $enrolment)
    {
        if (! Schema::hasTable('internship_task_assignments')) {
            return false;
        }
        $query = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereNotIn('status', ['cancelled', 'withdrawn', 'passed', 'completed']);
        if (Schema::hasColumn('internship_task_assignments', 'released_at')) {
            $query->whereNull('released_at');
        } else {
            $query->whereIn('status', ['locked']);
        }

        return $query->exists();
    }

    protected function eligibleAssignments(InternshipEnrolment $enrolment)
    {
        $query = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->whereIn('status', ['available', 'in_progress', 'revision_required']);
        if (Schema::hasColumn('internship_task_assignments', 'released_at')) {
            $query->whereNotNull('released_at');
        }

        return $query->orderBy('progression_day')->orderBy('id')->get();
    }

    protected function assignmentForDay(InternshipEnrolment $enrolment, $day)
    {
        if (! Schema::hasColumn('internship_task_assignments', 'progression_day')) {
            return null;
        }

        return InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
            ->where('progression_day', (int) $day)
            ->orderByDesc('id')
            ->first();
    }

    protected function isReleased(InternshipTaskAssignment $assignment)
    {
        if (! Schema::hasColumn('internship_task_assignments', 'released_at')) {
            return ! in_array($assignment->status, ['cancelled', 'withdrawn', 'locked'], true);
        }

        return $assignment->released_at !== null;
    }

    protected function taskPayload(InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment, array $context, $includeInstructions)
    {
        $task = $assignment->task;
        $instructions = [];
        if ($includeInstructions && $task) {
            foreach ($task->instructions() as $line) {
                $instructions[] = is_string($line) ? $line : (string) ($line['text'] ?? '');
            }
        }
        $slots = $task ? $task->evidenceSlots() : [];
        $this->audit($context, null, 'task_lookup', (string) $assignment->id);

        return [
            'success' => true,
            'locked' => false,
            'assignment_id' => $assignment->id,
            'program' => $this->programName($enrolment),
            'day' => $assignment->progression_day,
            'title' => $this->taskTitle($assignment),
            'status' => $assignment->status,
            'deadline' => $assignment->scheduled_work_date ? (string) $assignment->scheduled_work_date : null,
            'instructions' => array_values(array_filter($instructions)),
            'requirements' => $task ? (string) $task->submission_requirements : '',
            'slots' => $slots,
        ];
    }

    protected function taskTitle(InternshipTaskAssignment $assignment)
    {
        $task = $assignment->relationLoaded('task') ? $assignment->task : ($assignment->program_task_id ? $assignment->task : null);
        if ($task && ! empty($task->title)) {
            return $task->title;
        }

        return 'Task #'.$assignment->id;
    }

    protected function programName(InternshipEnrolment $enrolment)
    {
        if (! $enrolment->program_id || ! Schema::hasTable('internship_programs')) {
            return null;
        }
        $program = $enrolment->relationLoaded('program') ? $enrolment->program : InternshipProgram::find($enrolment->program_id);
        if (! $program) {
            return null;
        }

        return method_exists($program, 'displayName') ? $program->displayName() : $program->name;
    }

    protected function supervisorName(InternshipEnrolment $enrolment)
    {
        if (! $enrolment->supervisor_id) {
            return null;
        }
        $user = User::find($enrolment->supervisor_id);

        return $user ? $user->name : null;
    }

    protected function openIntake(array $context, User $user, InternshipEnrolment $enrolment, InternshipTaskAssignment $assignment)
    {
        $existing = InternshipIntake::where('conversation_id', $context['conversation_id'])
            ->where('assignment_id', $assignment->id)
            ->whereIn('status', [InternshipIntake::COLLECTING, InternshipIntake::AWAITING])
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $existing;
        }
        $intake = InternshipIntake::create([
            'conversation_id' => $context['conversation_id'],
            'intern_user_id' => $user->id,
            'enrolment_id' => $enrolment->id,
            'assignment_id' => $assignment->id,
            'status' => InternshipIntake::COLLECTING,
            'started_at' => now(),
            'expires_at' => Carbon::now()->addDays(7),
            'validation_state' => 'collecting',
        ]);
        $this->audit($context, $intake->id, 'intake_created', (string) $assignment->id);

        return $intake;
    }

    protected function captureLinks(InternshipIntake $intake, $text)
    {
        if (! preg_match_all('#https://github\.com/[A-Za-z0-9_./-]+#', (string) $text, $matches)) {
            return;
        }
        $links = $intake->links();
        foreach ($matches[0] as $url) {
            $url = rtrim($url, '.,)');
            if (! InternshipSubmissionFileGuard::isGithubUrl($url)) {
                continue;
            }
            if (! in_array($url, $links, true)) {
                $links[] = $url;
                InternshipIntakeFile::create([
                    'intake_id' => $intake->id,
                    'kind' => 'link',
                    'url' => $url,
                    'original_name' => 'github',
                    'status' => 'stored',
                ]);
            }
        }
        $intake->links_json = json_encode(array_values($links));
    }

    protected function storeLocalFile(InternshipIntake $intake, array $params, InternshipTaskAssignment $assignment)
    {
        $path = (string) $params['local_path'];
        if (! is_file($path)) {
            return ['success' => false, 'error' => 'media_failed'];
        }
        $name = isset($params['file_name']) ? $params['file_name'] : basename($path);
        $mime = isset($params['mime']) ? $params['mime'] : 'application/octet-stream';
        $size = filesize($path);
        $voice = $this->taskAllowsVoice($assignment);
        $check = InternshipSubmissionFileGuard::check($name, $mime, $size, $voice);
        if (empty($check['ok'])) {
            $this->audit(['conversation_id' => $intake->conversation_id], $intake->id, 'validation_failed', $check['error']);

            return ['success' => false, 'error' => $check['error'], 'max_bytes' => InternshipSubmissionFileGuard::maxBytes()];
        }
        $providerId = isset($params['provider_message_id']) ? $params['provider_message_id'] : null;
        if ($providerId) {
            $prior = InternshipIntakeFile::where('provider_message_id', $providerId)->first();
            if ($prior) {
                return ['success' => true, 'duplicate' => true, 'file_id' => $prior->id];
            }
        }
        $dir = 'internship/whatsapp-intake/'.$intake->id;
        $storedName = substr(sha1($check['name'].microtime(true)), 0, 10).'-'.$check['name'];
        $relative = $dir.'/'.$storedName;
        Storage::disk('local')->put($relative, file_get_contents($path));
        InternshipIntakeFile::create([
            'intake_id' => $intake->id,
            'provider_message_id' => $providerId,
            'kind' => 'file',
            'disk' => 'local',
            'path' => $relative,
            'original_name' => $check['name'],
            'mime' => $mime,
            'size' => $size,
            'checksum' => hash_file('sha256', $path),
            'status' => 'stored',
        ]);
        $this->audit(['conversation_id' => $intake->conversation_id], $intake->id, 'file_stored', $check['name']);

        return ['success' => true];
    }

    protected function queueMedia(InternshipIntake $intake, array $media, $providerMessageId)
    {
        $url = isset($media['url']) ? (string) $media['url'] : '';
        if ($url === '' || ! preg_match('#^https://#i', $url)) {
            $this->audit(['conversation_id' => $intake->conversation_id], $intake->id, 'media_failed', 'no_url');

            return ['success' => false, 'error' => 'media_failed'];
        }
        if ($providerMessageId && InternshipIntakeFile::where('provider_message_id', $providerMessageId)->exists()) {
            return ['success' => true, 'duplicate' => true];
        }
        $file = InternshipIntakeFile::create([
            'intake_id' => $intake->id,
            'provider_message_id' => $providerMessageId,
            'kind' => 'file',
            'url' => $url,
            'original_name' => isset($media['file_name']) ? InternshipSubmissionFileGuard::safeName($media['file_name']) : 'upload.bin',
            'mime' => isset($media['mimetype']) ? $media['mimetype'] : null,
            'size' => isset($media['file_length']) ? (int) $media['file_length'] : 0,
            'status' => 'pending',
        ]);
        \App\Jobs\RetrieveInternshipMedia::dispatch($file->id)->onQueue('whatsapp');
        $this->audit(['conversation_id' => $intake->conversation_id], $intake->id, 'media_queued', (string) $file->id);

        return ['success' => true, 'queued' => true];
    }

    public function completeMediaDownload(InternshipIntakeFile $file, $bytes, $mime)
    {
        $intake = $file->intake;
        $assignment = $intake ? InternshipTaskAssignment::find($intake->assignment_id) : null;
        $voice = $assignment ? $this->taskAllowsVoice($assignment) : false;
        $check = InternshipSubmissionFileGuard::check($file->original_name, $mime ?: $file->mime, strlen($bytes), $voice);
        if (empty($check['ok'])) {
            $file->status = 'rejected';
            $file->error = $check['error'];
            $file->save();
            $this->audit(['conversation_id' => $intake ? $intake->conversation_id : null], $file->intake_id, 'validation_failed', $check['error']);

            return ['success' => false, 'error' => $check['error']];
        }
        $dir = 'internship/whatsapp-intake/'.$file->intake_id;
        $relative = $dir.'/'.substr(sha1($check['name'].$file->id), 0, 10).'-'.$check['name'];
        Storage::disk('local')->put($relative, $bytes);
        $file->path = $relative;
        $file->disk = 'local';
        $file->mime = $mime ?: $file->mime;
        $file->size = strlen($bytes);
        $file->original_name = $check['name'];
        $file->checksum = hash('sha256', $bytes);
        $file->status = 'stored';
        $file->save();
        $this->audit(['conversation_id' => $intake ? $intake->conversation_id : null], $file->intake_id, 'file_stored', $check['name']);
        $this->tellIntern($intake, 'I saved '.$check['name'].'. Reply YES to submit it for supervisor review. Nothing has been submitted yet.');

        return ['success' => true];
    }

    public function failMedia(InternshipIntakeFile $file, $error)
    {
        $file->status = 'failed';
        $file->error = substr((string) $error, 0, 500);
        $file->save();
        $intake = $file->intake;
        if ($intake && $intake->status !== InternshipIntake::SUBMITTED) {
            $intake->error = $file->error;
            $intake->save();
        }
        $this->audit(['conversation_id' => $intake ? $intake->conversation_id : null], $file->intake_id, 'media_failed', $file->error);
        $this->tellIntern($intake, 'I could not save that file. Please send it again. Nothing was submitted.');
    }

    protected function missingRequirements(InternshipTaskAssignment $assignment, InternshipIntake $intake)
    {
        $task = $assignment->task;
        $blob = strtolower((string) ($task ? $task->submission_requirements : '').' '.json_encode($task ? $task->evidenceSlots() : []));
        $needsFile = $blob === '' || strpos($blob, 'file') !== false || strpos($blob, 'pdf') !== false
            || strpos($blob, 'screenshot') !== false || strpos($blob, 'image') !== false || strpos($blob, 'doc') !== false;
        $needsGithub = strpos($blob, 'github') !== false;
        $files = $intake->files()->where('status', 'stored')->where('kind', 'file')->count();
        $textOnly = trim((string) $intake->text_body);
        if ($needsGithub && $intake->links() === [] && $files === 0) {
            return 'github';
        }
        if ($needsFile && $files === 0 && ! $this->allowsTextOnly($assignment)) {
            if ($textOnly !== '' && ! preg_match('/https?:\/\//i', $textOnly)) {
                return strpos($blob, 'pdf') !== false ? 'pdf' : 'file';
            }
            if ($files === 0 && $intake->links() === []) {
                return strpos($blob, 'pdf') !== false ? 'pdf' : 'file';
            }
        }

        return null;
    }

    protected function allowsTextOnly(InternshipTaskAssignment $assignment)
    {
        $task = $assignment->task;
        $blob = strtolower((string) ($task ? $task->submission_requirements : ''));

        return strpos($blob, 'text') !== false && strpos($blob, 'pdf') === false && strpos($blob, 'file') === false && strpos($blob, 'github') === false;
    }

    protected function taskAllowsVoice(InternshipTaskAssignment $assignment)
    {
        $task = $assignment->task;
        $blob = strtolower((string) ($task ? $task->submission_requirements : '').' '.json_encode($task ? $task->evidenceSlots() : []));

        return strpos($blob, 'audio') !== false || strpos($blob, 'voice') !== false;
    }

    protected function mediaStillPending(InternshipIntake $intake)
    {
        if (! $intake->files()->where('status', 'pending')->exists()) {
            return false;
        }

        return $intake->files()->where('status', 'stored')->where('kind', 'file')->count() === 0
            && $intake->links() === [];
    }

    protected function rememberProvider(InternshipIntake $intake, $providerId)
    {
        $providerId = trim((string) $providerId);
        if ($providerId === '') {
            return;
        }
        $ids = array_filter(array_map('trim', explode(',', (string) $intake->provider_message_ids)));
        if (! in_array($providerId, $ids, true)) {
            $ids[] = $providerId;
            $intake->provider_message_ids = implode(',', $ids);
        }
    }

    protected function tellIntern($intake, $body)
    {
        if (! $intake || ! $intake->conversation_id) {
            return;
        }
        $conversation = WhatsAppConversation::find($intake->conversation_id);
        if (! $conversation || $conversation->mode !== WhatsAppConversation::MODE_AI) {
            return;
        }
        app(\App\Services\WhatsApp\WhatsAppConversationService::class)->assistantReply($conversation, $body);
    }

    protected function isConfirmation($text, InternshipIntake $intake)
    {
        $text = strtolower(trim((string) $text));
        if ($intake->files()->where('status', 'stored')->count() === 0 && $intake->links() === []) {
            return false;
        }

        return (bool) preg_match('/^(yes|confirm|submit)( these| it| them| this)?[.!]?$|\b(submit these|confirm the submission|yes,? submit)\b/', $text);
    }

    protected function intakeSummary(InternshipIntake $intake, InternshipTaskAssignment $assignment, InternshipEnrolment $enrolment, $submitted)
    {
        $files = [];
        foreach ($intake->files()->where('status', 'stored')->get() as $file) {
            $files[] = $file->kind === 'link' ? $file->url : $file->original_name;
        }

        return [
            'success' => true,
            'awaiting_confirm' => ! $submitted,
            'needs_choice' => false,
            'title' => $this->taskTitle($assignment),
            'day' => $assignment->progression_day,
            'program' => $this->programName($enrolment),
            'files' => $files,
            'assignment_id' => $assignment->id,
            'enrolment_id' => $enrolment->id,
        ];
    }

    protected function requestedDay($text, array $params)
    {
        if (! empty($params['day'])) {
            return (int) $params['day'];
        }
        if (preg_match('/\bday\s*(\d{1,3})\b/i', (string) $text, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    protected function choiceIndex(array $params)
    {
        if (isset($params['choice']) && $params['choice'] !== '') {
            $n = (int) $params['choice'];

            return $n > 0 ? $n - 1 : null;
        }
        $text = trim(isset($params['text']) ? (string) $params['text'] : '');
        if (preg_match('/^\s*(\d{1,2})\b/', $text, $match)) {
            return ((int) $match[1]) - 1;
        }

        return null;
    }

    protected function stampSource(InternshipSubmission $submission, InternshipIntake $intake)
    {
        $dirty = false;
        if (Schema::hasColumn('internship_submissions', 'source')) {
            $submission->source = 'whatsapp';
            $dirty = true;
        }
        if (Schema::hasColumn('internship_submissions', 'whatsapp_conversation_id')) {
            $submission->whatsapp_conversation_id = $intake->conversation_id;
            $dirty = true;
        }
        if (Schema::hasColumn('internship_submissions', 'whatsapp_message_ids')) {
            $submission->whatsapp_message_ids = $intake->provider_message_ids;
            $dirty = true;
        }
        if ($dirty) {
            $submission->save();
        }
    }

    protected function studentAlreadyNotified(InternshipSubmission $submission, User $user)
    {
        if (! Schema::hasTable('internship_notification_logs')) {
            return false;
        }
        $key = 'submission:'.$submission->id.':student:'.$user->id;

        return DB::table('internship_notification_logs')->where('idempotency_key', $key)->where('status', '!=', 'failed')->exists();
    }

    protected function audit(array $context, $intakeId, $type, $body)
    {
        if (! Schema::hasTable('whatsapp_internship_activities')) {
            return;
        }
        InternshipActivity::create([
            'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
            'intake_id' => $intakeId,
            'actor_user_id' => isset($context['intern_user_id']) ? $context['intern_user_id'] : null,
            'type' => $type,
            'body' => substr((string) $body, 0, 500),
        ]);
    }
}

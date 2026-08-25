<?php

namespace App\Http\Controllers\Internship;

use App\Http\Controllers\Controller;
use App\InternshipDraftFile;
use App\InternshipEnrolment;
use App\InternshipSupervisorMessage;
use App\InternshipTaskAssignment;
use App\Services\Internship\InternshipProgramService;
use App\Services\Internship\InternSupervisorChat;
use App\Services\TimesheetService;
use App\TimesheetEntry;
use App\Support\InternCompliance;
use App\User;
use App\Support\InternshipHandbook;
use App\Support\InternshipRubric;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class InternshipStudentController extends Controller
{
    protected $service;
    protected $all_permission = [];

    public function __construct(InternshipProgramService $service)
    {
        $this->service = $service;
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach ($role->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }

            return $next($request);
        });
    }

    protected function allowStudent()
    {
        if (in_array('internship.student', $this->all_permission, true)
            || in_array('internship_module', $this->all_permission, true)
            || Auth::user()->role_id <= 2) {
            return;
        }
        abort(403, 'Student internship access denied.');
    }

    /**
     * Intern home shown at /admin (and after login). Sales dashboard is empty for Interns.
     */
    public function renderHome(User $user)
    {
        $pending = $this->service->pendingForStudent($user) ?: [];
        $enrolment = $pending['enrolment'] ?? null;
        $assignment = $pending['assignment'] ?? null;
        $lastPassed = $pending['last_passed'] ?? null;
        $isWorkingToday = $enrolment
            ? $this->service->isWorkingDate($user, now())
            : false;
        $supervisors = $enrolment ? $this->service->studentSupervisors($enrolment) : [];
        $requestState = $enrolment
            ? $this->service->studentTaskRequestState($enrolment, $user)
            : null;
        $gradeSummary = $this->service->studentGradeSummary($assignment ?: $lastPassed);

        $timesheet = app(TimesheetService::class);
        $weekScore = $timesheet->weekScore($user->id);
        $dayBalance = $timesheet->dayBalance($user->id, date('Y-m-d'));
        $totalHours = round((float) TimesheetEntry::where('user_id', $user->id)->sum('hours'), 2);

        $openStatuses = ['available', 'in_progress', 'revision_required', 'submitted'];
        $currentTaskCount = 0;
        $awaiting = collect();
        $awaitingGradingCount = 0;
        if ($enrolment) {
            $currentTaskCount = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereIn('status', $openStatuses)
                ->count();
            $awaiting = $this->awaitingAssignments($enrolment);
            $awaitingGradingCount = $awaiting->count();
        }

        $byActivity = [];
        $byCategory = [];
        $entries = TimesheetEntry::with('activity.categoryRel')->where('user_id', $user->id)->get();
        foreach ($entries as $entry) {
            $actName = $entry->activity_name ?: optional($entry->activity)->name ?: 'Uncategorized';
            $byActivity[$actName] = ($byActivity[$actName] ?? 0) + (float) $entry->hours;
            $cat = optional(optional($entry->activity)->categoryRel)->name
                ?: (optional($entry->activity)->category ?: 'Uncategorized');
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + (float) $entry->hours;
        }
        arsort($byActivity);
        arsort($byCategory);

        $dowShort = [
            'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
            'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
        ];
        $weekChart = ['labels' => [], 'logged' => [], 'expected' => []];
        foreach ($weekScore['days'] as $day) {
            $weekChart['labels'][] = $dowShort[$day['day']] ?? $day['day'];
            $weekChart['logged'][] = $day['logged'];
            $weekChart['expected'][] = $day['expected'];
        }

        return view('internship.student.home', compact(
            'enrolment',
            'assignment',
            'lastPassed',
            'isWorkingToday',
            'supervisors',
            'requestState',
            'gradeSummary',
            'weekScore',
            'dayBalance',
            'totalHours',
            'currentTaskCount',
            'awaiting',
            'awaitingGradingCount',
            'byActivity',
            'byCategory',
            'weekChart'
        ));
    }

    public function dashboard()
    {
        $this->allowStudent();
        $pending = $this->service->pendingForStudent(Auth::user()) ?: [];
        $enrolment = $pending['enrolment'] ?? null;
        $assignment = $pending['assignment'] ?? null;
        $lastPassed = $pending['last_passed'] ?? null;
        $isWorkingToday = $enrolment
            ? $this->service->isWorkingDate(Auth::user(), now())
            : false;
        $supervisors = $enrolment ? $this->service->studentSupervisors($enrolment) : [];
        $requestState = $enrolment
            ? $this->service->studentTaskRequestState($enrolment, Auth::user())
            : null;
        $gradeSummary = $this->service->studentGradeSummary($assignment ?: $lastPassed);
        $awaiting = $this->awaitingAssignments($enrolment);
        $awaitingGradingCount = $awaiting->count();

        return view('internship.student.dashboard', compact(
            'enrolment',
            'assignment',
            'lastPassed',
            'isWorkingToday',
            'supervisors',
            'requestState',
            'gradeSummary',
            'awaiting',
            'awaitingGradingCount'
        ));
    }

    public function requestTask()
    {
        $this->allowStudent();
        $pending = $this->service->pendingForStudent(Auth::user());
        $enrolment = $pending['enrolment'] ?? null;
        if (! $enrolment) {
            return redirect()->route('internship.student.dashboard')
                ->with('not_permitted', 'You are not enrolled in an internship program yet.');
        }

        try {
            $assignment = $this->service->requestNextTaskForStudent($enrolment, Auth::user());
        } catch (\Throwable $e) {
            return redirect()->route('internship.student.dashboard')
                ->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('internship.student.task', $assignment->id)
            ->with('message', 'Task #'.$assignment->progression_day.' is ready. Read the instructions, then upload your work when you finish.');
    }

    public function task($id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with([
            'task',
            'enrolment.program',
            'enrolment.supervisor',
            'submissions.files',
            'submissions.grades.grader',
        ])->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }
        $program = $assignment->enrolment->program;
        $handbookPath = ($program && $assignment->task)
            ? InternshipHandbook::absolutePath($program, $assignment->task)
            : null;
        $hasHandbook = (bool) $handbookPath;
        $stepProgress = $assignment->stepProgress();
        $supervisors = $this->service->studentSupervisors($assignment->enrolment);
        $gradeSummary = $this->service->studentGradeSummary($assignment);
        $criteria = InternshipRubric::criteria($assignment->task);
        $evidenceSlots = $assignment->task ? $assignment->task->evidenceSlots() : [];
        $drafts = $this->service->assignmentDrafts($assignment, Auth::user());
        $draftsBySlot = $drafts->keyBy('slot_index');

        return view('internship.student.task', compact(
            'assignment',
            'hasHandbook',
            'stepProgress',
            'supervisors',
            'gradeSummary',
            'criteria',
            'evidenceSlots',
            'drafts',
            'draftsBySlot'
        ));
    }

    public function updateStepProgress(Request $request, $id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with(['task', 'enrolment'])->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }
        if (! in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Task is not editable.'], 422);
            }

            return back()->with('not_permitted', 'Checklist can only be updated while the task is open.');
        }

        $data = $request->validate([
            'checked' => 'nullable|array',
            'checked.*' => 'integer|min:0|max:500',
        ]);
        $progress = $this->service->updateAssignmentStepProgress($assignment, $data['checked'] ?? []);

        if ($assignment->status === 'available') {
            try {
                $this->service->startAssignment($assignment, Auth::user());
            } catch (\Throwable $e) {
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'progress' => $progress]);
        }

        return back()->with('message', 'Checklist updated ('.$progress['done'].'/'.$progress['total'].').');
    }

    public function downloadHandbook($id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with(['task', 'enrolment.program'])->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }
        $program = $assignment->enrolment->program;
        $task = $assignment->task;
        if (! $program || ! $task) {
            abort(404, 'Handbook not available.');
        }
        $path = InternshipHandbook::absolutePath($program, $task);
        if (! $path) {
            abort(404, 'Handbook file not found for this day.');
        }

        return response()->download($path, InternshipHandbook::downloadName($program, $task));
    }

    public function start($id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with('enrolment')->findOrFail($id);
        $this->service->startAssignment($assignment, Auth::user());

        return redirect()->route('internship.student.task', $id)->with('message', 'Task started. Complete the work and submit evidence.');
    }

    public function storeDraft(Request $request, $id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with('enrolment')->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }
        $data = $request->validate([
            'file' => 'required',
            'slot' => 'nullable|integer|min:0|max:39',
            'caption' => 'nullable|string|max:400',
        ]);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['success' => false, 'error' => 'Choose or paste a file first.'], 422);
        }

        try {
            $draft = $this->service->storeDraftFile(
                $assignment,
                Auth::user(),
                $file,
                $data['slot'] ?? 0,
                $data['caption'] ?? ''
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'id' => $draft->id,
            'name' => $draft->original_name,
            'size' => (int) $draft->size,
            'size_mb' => number_format(((int) $draft->size) / 1048576, 2),
            'is_image' => $draft->isImage(),
            'caption' => $draft->caption,
            'url' => route('internship.student.draft', $draft->id),
        ]);
    }

    public function destroyDraft($id, $draftId)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with('enrolment')->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }
        $draft = InternshipDraftFile::where('id', $draftId)
            ->where('assignment_id', $assignment->id)
            ->firstOrFail();
        $this->service->deleteDraftFile($draft, Auth::user());

        return response()->json(['success' => true]);
    }

    public function updateDraft(Request $request, $id, $draftId)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with('enrolment')->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }
        $data = $request->validate([
            'caption' => 'nullable|string|max:400',
        ]);
        $draft = InternshipDraftFile::where('id', $draftId)
            ->where('assignment_id', $assignment->id)
            ->firstOrFail();
        $this->service->updateDraftCaption($draft, Auth::user(), $data['caption'] ?? '');

        return response()->json(['success' => true]);
    }

    public function downloadDraft($draftId)
    {
        $this->allowStudent();
        $draft = InternshipDraftFile::with('assignment.enrolment')->findOrFail($draftId);
        $enrolment = $draft->assignment->enrolment;
        $isOwner = (int) $draft->student_user_id === (int) Auth::id();
        if (! $isOwner
            && ! $enrolment->isSupervisedBy(Auth::id())
            && ! InternCompliance::isInternshipAdmin(Auth::user())) {
            abort(403);
        }
        if (! Storage::disk($draft->disk ?: 'local')->exists($draft->path)) {
            abort(404);
        }

        return Storage::disk($draft->disk ?: 'local')->download($draft->path, $draft->original_name);
    }

    public function submit(Request $request, $id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with('enrolment')->findOrFail($id);
        $data = $request->validate([
            'description' => 'required|string|min:20',
            'evidence' => 'nullable|array|max:40',
            'evidence.*.caption' => 'nullable|string|max:400',
            'draft_captions' => 'nullable|array',
            'draft_captions.*' => 'nullable|string|max:400',
        ]);

        foreach ((array) ($data['draft_captions'] ?? []) as $draftId => $caption) {
            $draft = InternshipDraftFile::where('id', (int) $draftId)
                ->where('assignment_id', $assignment->id)
                ->first();
            if ($draft) {
                $this->service->updateDraftCaption($draft, Auth::user(), $caption);
            }
        }

        $collected = $this->collectEvidenceUploads($request, $data);
        if (! empty($collected['error'])) {
            return back()->withInput()->withErrors(['evidence' => $collected['error']]);
        }
        $items = $collected['items'];
        $draftCount = $this->service->assignmentDrafts($assignment, Auth::user())->count();
        if (count($items) < 1 && $draftCount < 1) {
            return back()->withInput()->withErrors([
                'evidence' => 'Upload at least one file. Each file is saved as soon as you add it — then click Submit for grading.',
            ]);
        }

        try {
            $this->service->submitAssignment($assignment, Auth::user(), $data['description'], $items);
        } catch (\Throwable $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        try {
            $date = \Carbon\Carbon::parse($assignment->scheduled_work_date)->toDateString();
        } catch (\Throwable $e) {
            $date = date('Y-m-d');
        }

        return redirect()->route('timesheet.fill', ['date' => $date, 'intern' => 1, 'assignment' => $assignment->id])
            ->with('message', 'Submission sent. Your supervisor reviews it, and your next task arrives on your next working day once accepted. Please log today’s hours.');
    }

    public function upload()
    {
        $this->allowStudent();
        $enrolment = InternshipEnrolment::with(['program', 'assignments.task'])
            ->where('student_user_id', Auth::id())
            ->orderByDesc('id')
            ->first();
        $supervisors = $enrolment ? $this->service->studentSupervisors($enrolment) : [];
        $assignments = $enrolment ? $enrolment->assignments->sortBy('progression_day') : collect();
        $uploadable = $assignments->whereIn('status', ['available', 'in_progress', 'revision_required'])->values();
        $awaiting = $assignments->where('status', 'submitted')->values();
        $awaitingGradingCount = $awaiting->count();

        return view('internship.student.upload', compact(
            'enrolment',
            'supervisors',
            'uploadable',
            'awaiting',
            'awaitingGradingCount'
        ));
    }

    public function messages()
    {
        $this->allowStudent();
        $enrolment = InternshipEnrolment::with('program')->where('student_user_id', Auth::id())->orderByDesc('id')->first();
        $supervisors = $enrolment ? $this->service->studentSupervisors($enrolment) : [];
        $threads = $enrolment
            ? InternshipSupervisorMessage::where('enrolment_id', $enrolment->id)->latest()->limit(40)->get()
            : collect();
        $awaiting = $this->awaitingAssignments($enrolment);
        $awaitingGradingCount = $awaiting->count();
        $selectedPhone = preg_replace('/\D+/', '', (string) request('phone', ''));

        return view('internship.student.messages', compact(
            'enrolment',
            'supervisors',
            'threads',
            'awaiting',
            'awaitingGradingCount',
            'selectedPhone'
        ));
    }

    public function sendMessage(Request $request, InternSupervisorChat $chat)
    {
        $this->allowStudent();
        $enrolment = InternshipEnrolment::with('program')->where('student_user_id', Auth::id())->orderByDesc('id')->firstOrFail();
        $data = $request->validate([
            'supervisor_phone' => 'required|string|max:40',
            'body' => 'required|string|min:2|max:2000',
        ]);

        $wanted = preg_replace('/\D+/', '', $data['supervisor_phone']);
        $match = collect($this->service->studentSupervisors($enrolment))->first(function ($row) use ($wanted) {
            return preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) === $wanted;
        });
        if (! $match) {
            return back()->withInput()->with('not_permitted', 'Choose a supervisor from your assigned list.');
        }

        try {
            $chat->sendFromStudent(Auth::user(), $enrolment, $match, $data['body']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'Message sent. You and your supervisor both received a WhatsApp copy. They reply from the link, and you will receive their reply here on WhatsApp.');
    }

    public function portfolio()
    {
        $this->allowStudent();
        $enrolment = InternshipEnrolment::with(['program', 'assignments' => function ($q) {
            $q->orderBy('progression_day');
        }, 'assignments.task', 'assignments.latestSubmission.grades.grader'])
            ->where('student_user_id', Auth::id())
            ->orderByDesc('id')
            ->first();
        $supervisors = $enrolment ? $this->service->studentSupervisors($enrolment) : [];

        return view('internship.student.portfolio', compact('enrolment', 'supervisors'));
    }

    public function downloadFile($fileId)
    {
        $this->allowStudent();
        $file = \App\InternshipSubmissionFile::with('submission.assignment.enrolment')->findOrFail($fileId);
        $enrolment = $file->submission->assignment->enrolment;
        $isOwner = (int) $enrolment->student_user_id === (int) Auth::id();
        if (! $isOwner
            && ! $enrolment->isSupervisedBy(Auth::id())
            && ! InternCompliance::isInternshipAdmin(Auth::user())) {
            abort(403, 'This evidence file belongs to another intern.');
        }
        if (! Storage::disk($file->disk ?: 'local')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk($file->disk ?: 'local')->download($file->path, $file->original_name);
    }

    protected function awaitingAssignments($enrolment)
    {
        if (! $enrolment) {
            return collect();
        }
        if ($enrolment->relationLoaded('assignments')) {
            return $enrolment->assignments->where('status', 'submitted')->sortBy('progression_day')->values();
        }

        return InternshipTaskAssignment::with('task')
            ->where('enrolment_id', $enrolment->id)
            ->where('status', 'submitted')
            ->orderBy('progression_day')
            ->get();
    }

    /**
     * Pull uploaded evidence without mime/type rules so any file the intern
     * attaches can be submitted. Empty unused slots are ignored.
     *
     * @return array{items:array<int,array{file:UploadedFile,caption:string}>,error:?string}
     */
    protected function collectEvidenceUploads(Request $request, array $data)
    {
        $maxBytes = 20 * 1024 * 1024;
        $items = [];

        $push = function ($file, $caption) use (&$items, $maxBytes) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                return null;
            }
            if (! $file->isValid()) {
                return 'One of the files did not finish uploading. Try a smaller file or paste the screenshot again.';
            }
            if ((int) $file->getSize() > $maxBytes) {
                return 'Each file must be 20 MB or smaller after compression.';
            }
            $items[] = [
                'file' => $file,
                'caption' => trim((string) $caption),
            ];

            return null;
        };

        foreach ((array) $request->file('evidence', []) as $index => $row) {
            $file = is_array($row) ? ($row['file'] ?? null) : $row;
            $error = $push($file, data_get($data, 'evidence.'.$index.'.caption', ''));
            if ($error) {
                return ['items' => [], 'error' => $error];
            }
        }
        foreach ((array) $request->file('files', []) as $file) {
            $error = $push($file, '');
            if ($error) {
                return ['items' => [], 'error' => $error];
            }
        }

        return ['items' => $items, 'error' => null];
    }
}

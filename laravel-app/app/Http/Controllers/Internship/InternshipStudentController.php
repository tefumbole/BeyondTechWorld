<?php

namespace App\Http\Controllers\Internship;

use App\Http\Controllers\Controller;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
use App\Services\Internship\InternshipProgramService;
use App\Services\TimesheetService;
use App\TimesheetEntry;
use App\Support\InternCompliance;
use App\User;
use App\Support\InternshipHandbook;
use App\Support\InternshipRubric;
use Illuminate\Http\Request;
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
        $pending = $this->service->pendingForStudent($user);
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
        if ($enrolment) {
            $currentTaskCount = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereIn('status', $openStatuses)
                ->count();
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
            'byActivity',
            'byCategory',
            'weekChart'
        ));
    }

    public function dashboard()
    {
        $this->allowStudent();
        $pending = $this->service->pendingForStudent(Auth::user());
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

        return view('internship.student.dashboard', compact(
            'enrolment',
            'assignment',
            'lastPassed',
            'isWorkingToday',
            'supervisors',
            'requestState',
            'gradeSummary'
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

        return view('internship.student.task', compact(
            'assignment',
            'hasHandbook',
            'stepProgress',
            'supervisors',
            'gradeSummary',
            'criteria',
            'evidenceSlots'
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

    public function submit(Request $request, $id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with('enrolment')->findOrFail($id);
        $data = $request->validate([
            'description' => 'required|string|min:20',
            'evidence' => 'required|array|min:1|max:15',
            'evidence.*.file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240',
            'evidence.*.caption' => 'nullable|string|max:400',
            'files' => 'nullable|array|max:15',
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240',
        ]);

        $items = [];
        foreach ((array) $request->file('evidence', []) as $index => $row) {
            $file = is_array($row) ? ($row['file'] ?? null) : $row;
            if (! $file) {
                continue;
            }
            $caption = trim((string) data_get($data, 'evidence.'.$index.'.caption', ''));
            $items[] = ['file' => $file, 'caption' => $caption];
        }
        foreach ((array) $request->file('files', []) as $file) {
            if ($file) {
                $items[] = ['file' => $file, 'caption' => ''];
            }
        }
        if (count($items) < 1) {
            return back()->withInput()->withErrors([
                'evidence' => 'Attach at least one screenshot or PDF. Use Add another screenshot if you need more than the slots shown.',
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
}

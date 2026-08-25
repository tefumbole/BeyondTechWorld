<?php

namespace App\Http\Controllers\Internship;

use App\Http\Controllers\Controller;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
use App\Services\Internship\InternshipProgramService;
use App\Support\InternshipHandbook;
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

        return view('internship.student.task', compact(
            'assignment',
            'hasHandbook',
            'stepProgress',
            'supervisors',
            'gradeSummary'
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
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240',
        ]);
        try {
            $this->service->submitAssignment($assignment, Auth::user(), $data['description'], $request->file('files', []));
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
        if ((int) $file->submission->assignment->enrolment->student_user_id !== (int) Auth::id()
            && Auth::user()->role_id > 2
            && ! in_array('internship.submissions.view', $this->all_permission, true)) {
            abort(403);
        }
        if (! Storage::disk($file->disk ?: 'local')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk($file->disk ?: 'local')->download($file->path, $file->original_name);
    }
}

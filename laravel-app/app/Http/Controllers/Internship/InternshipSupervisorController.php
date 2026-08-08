<?php

namespace App\Http\Controllers\Internship;

use App\Http\Controllers\Controller;
use App\InternshipEnrolment;
use App\InternshipSubmission;
use App\Services\Internship\InternshipProgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class InternshipSupervisorController extends Controller
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

    protected function allow()
    {
        if (in_array('internship.supervise', $this->all_permission, true)
            || in_array('internship.submissions.grade', $this->all_permission, true)
            || Auth::user()->role_id <= 2) {
            return;
        }
        abort(403, 'Supervisor access denied.');
    }

    public function index()
    {
        $this->allow();
        $q = InternshipSubmission::with([
            'student', 'assignment.task', 'assignment.enrolment.program', 'files',
        ])->where('status', 'submitted')->orderByDesc('submitted_at');

        if (Auth::user()->role_id > 2 && ! in_array('internship.enrolments.view', $this->all_permission, true)) {
            $uid = (int) Auth::id();
            $q->whereHas('assignment.enrolment', function ($w) use ($uid) {
                $w->where('supervisor_id', $uid)
                    ->orWhere('supervisors_json', 'like', '%user:'.$uid.'%');
            });
        }

        $submissions = $q->paginate(30);

        return view('internship.supervisor.index', compact('submissions'));
    }

    public function show($id)
    {
        $this->allow();
        $submission = InternshipSubmission::with([
            'student', 'files', 'grades.grader',
            'assignment.task', 'assignment.enrolment.program', 'assignment.enrolment.supervisor',
        ])->findOrFail($id);

        $enrolment = $submission->assignment->enrolment;
        $isAssignedSupervisor = $enrolment->isSupervisedBy(Auth::id());
        if (Auth::user()->role_id > 2
            && ! $isAssignedSupervisor
            && ! in_array('internship.submissions.grade', $this->all_permission, true)) {
            if (! in_array('internship.programs.view', $this->all_permission, true)) {
                abort(403);
            }
        }
        if (Auth::user()->role_id > 2
            && ! $isAssignedSupervisor
            && ! in_array('internship.enrolments.create', $this->all_permission, true)
            && ! in_array('internship.programs.import', $this->all_permission, true)) {
            if (! in_array('internship.submissions.grade', $this->all_permission, true)
                || ! in_array('internship.dashboard.view', $this->all_permission, true)) {
                abort(403);
            }
        }

        $rubric = $submission->assignment->task->rubric();

        return view('internship.supervisor.show', compact('submission', 'rubric'));
    }

    public function grade(Request $request, $id)
    {
        $this->allow();
        $submission = InternshipSubmission::with('assignment.enrolment', 'assignment.task')->findOrFail($id);
        if (Auth::user()->role_id > 2
            && ! $submission->assignment->enrolment->isSupervisedBy(Auth::id())
            && ! in_array('internship.programs.import', $this->all_permission, true)
            && ! in_array('internship.enrolments.create', $this->all_permission, true)) {
            if (! in_array('internship.submissions.request_revision', $this->all_permission, true)) {
                abort(403);
            }
        }

        $data = $request->validate([
            'decision' => 'required|in:pass,revision_required',
            'feedback' => 'nullable|string|max:5000',
            'score' => 'nullable|integer|min:0|max:100',
            'rubric_scores' => 'nullable|array',
            'rubric_scores.*' => 'nullable|integer|min:0|max:40',
        ]);

        $this->service->gradeSubmission($submission, Auth::user(), $data);

        return redirect()->route('internship.supervisor.index')->with('message', 'Grade saved.');
    }

    public function downloadFile($fileId)
    {
        $this->allow();
        $file = \App\InternshipSubmissionFile::with('submission.assignment.enrolment')->findOrFail($fileId);
        $enrolment = $file->submission->assignment->enrolment;
        if (Auth::user()->role_id > 2
            && ! $enrolment->isSupervisedBy(Auth::id())
            && ! in_array('internship.submissions.view', $this->all_permission, true)) {
            abort(403);
        }
        if (! Storage::disk($file->disk ?: 'local')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk($file->disk ?: 'local')->download($file->path, $file->original_name);
    }

    public function students()
    {
        $this->allow();
        $q = InternshipEnrolment::with(['student', 'program'])
            ->whereIn('status', ['pending', 'active', 'paused'])
            ->orderByDesc('id');
        if (Auth::user()->role_id > 2 && ! in_array('internship.enrolments.create', $this->all_permission, true)) {
            $uid = (int) Auth::id();
            $q->where(function ($w) use ($uid) {
                $w->where('supervisor_id', $uid)
                    ->orWhere('supervisors_json', 'like', '%user:'.$uid.'%');
            });
        }
        $enrolments = $q->paginate(40);

        return view('internship.supervisor.students', compact('enrolments'));
    }

    public function placeEdit($id)
    {
        $this->allow();
        $enrolment = InternshipEnrolment::with(['student', 'program', 'assignments'])->findOrFail($id);
        $this->assertCanPlace($enrolment);
        $hasAssignments = $enrolment->assignments->isNotEmpty();
        $hasOpen = $enrolment->assignments->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])->isNotEmpty();

        return view('internship.supervisor.place', compact('enrolment', 'hasAssignments', 'hasOpen'));
    }

    public function placeUpdate(Request $request, $id)
    {
        $this->allow();
        $enrolment = InternshipEnrolment::findOrFail($id);
        $this->assertCanPlace($enrolment);
        $data = $request->validate([
            'start_date' => 'nullable|date',
            'start_curriculum_day' => 'nullable|integer|min:1|max:180',
            'next_curriculum_day' => 'nullable|integer|min:1|max:180',
            'planned_duration_days' => \App\Application::internshipDurationRule(false),
            'notes' => 'nullable|string|max:2000',
        ]);
        try {
            $this->service->updatePlacement($enrolment, $data);
            if ($enrolment->fresh()->status === 'active') {
                $this->service->reconcileReleases($enrolment->id);
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('internship.supervisor.students')->with('message', 'Placement updated. Tasks will follow the new start day.');
    }

    protected function assertCanPlace(InternshipEnrolment $enrolment)
    {
        if (Auth::user()->role_id <= 2
            || in_array('internship.enrolments.update', $this->all_permission, true)
            || in_array('internship.enrolments.create', $this->all_permission, true)) {
            return;
        }
        if ($enrolment->isSupervisedBy(Auth::id())) {
            return;
        }
        abort(403, 'You can only place students assigned to you.');
    }
}

<?php

namespace App\Http\Controllers\Internship;

use App\Http\Controllers\Controller;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
use App\Services\Internship\InternshipProgramService;
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
        $isWorkingToday = $enrolment
            ? $this->service->isWorkingDate(Auth::user(), now())
            : false;

        return view('internship.student.dashboard', compact('enrolment', 'assignment', 'isWorkingToday'));
    }

    public function task($id)
    {
        $this->allowStudent();
        $assignment = InternshipTaskAssignment::with(['task', 'enrolment.program', 'submissions.files', 'submissions.grades'])
            ->findOrFail($id);
        if ((int) $assignment->enrolment->student_user_id !== (int) Auth::id()) {
            abort(403);
        }

        return view('internship.student.task', compact('assignment'));
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

        return redirect()->route('internship.student.dashboard')->with('message', 'Submission sent. Your supervisor will grade it. The next task unlocks on your next working day after a Pass.');
    }

    public function portfolio()
    {
        $this->allowStudent();
        $enrolment = InternshipEnrolment::with(['program', 'assignments' => function ($q) {
            $q->where('status', 'passed')->orderBy('progression_day');
        }, 'assignments.task', 'assignments.latestSubmission'])
            ->where('student_user_id', Auth::id())
            ->orderByDesc('id')
            ->first();

        return view('internship.student.portfolio', compact('enrolment'));
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

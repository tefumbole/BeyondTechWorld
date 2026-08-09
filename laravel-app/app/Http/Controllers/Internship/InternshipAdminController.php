<?php

namespace App\Http\Controllers\Internship;

use App\Application;
use App\Http\Controllers\Controller;
use App\InternshipEnrolment;
use App\InternshipProgram;
use App\InternshipProgramTask;
use App\Services\Internship\InternshipProgramService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class InternshipAdminController extends Controller
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

    protected function allow($perm)
    {
        if (Auth::user() && (int) Auth::user()->role_id <= 2) {
            return;
        }
        if (in_array($perm, $this->all_permission, true)) {
            return;
        }
        // Read-only helpers: module flag + programs.view cover listing/detail.
        $readAliases = [
            'internship.dashboard.view',
            'internship.programs.view',
            'internship.enrolments.view',
            'internship.reports.view',
        ];
        if (in_array($perm, $readAliases, true)
            && (in_array('internship_module', $this->all_permission, true)
                || in_array('internship.programs.view', $this->all_permission, true))) {
            return;
        }
        abort(403, 'Internship access denied.');
    }

    public function dashboard()
    {
        $this->allow('internship.dashboard.view');
        $stats = [
            'programs' => InternshipProgram::where('status', 'published')->count(),
            'active_enrolments' => InternshipEnrolment::where('status', 'active')->count(),
            'completed' => InternshipEnrolment::where('status', 'completed')->count(),
            'pending_review' => DB::table('internship_task_assignments')->where('status', 'submitted')->count(),
        ];

        return view('internship.admin.dashboard', compact('stats'));
    }

    public function programs()
    {
        $this->allow('internship.programs.view');
        $programs = InternshipProgram::withCount('tasks')->orderBy('name')->orderByDesc('version')->get();

        return view('internship.admin.programs', compact('programs'));
    }

    public function programShow($id)
    {
        $this->allow('internship.programs.view');
        $program = InternshipProgram::with(['tasks' => function ($q) {
            $q->orderBy('day_number');
        }])->findOrFail($id);
        $canEdit = in_array('internship.programs.update', $this->all_permission, true)
            || in_array('internship.tasks.update', $this->all_permission, true)
            || Auth::user()->role_id <= 2;

        return view('internship.admin.program_show', compact('program', 'canEdit'));
    }

    public function programUpdate(Request $request, $id)
    {
        $this->allow('internship.programs.update');
        $program = InternshipProgram::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100',
            'description' => 'nullable|string|max:5000',
            'status' => 'required|in:draft,published,archived',
            'discipline' => 'nullable|string|max:255',
            'prerequisites' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
        $program->fill([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'discipline' => $data['discipline'] ?? null,
            'prerequisites' => $data['prerequisites'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $program->save();

        return back()->with('message', 'Program details saved.');
    }

    public function taskUpdate(Request $request, $id, $taskId)
    {
        $this->allow('internship.tasks.update');
        $program = InternshipProgram::findOrFail($id);
        $task = InternshipProgramTask::where('program_id', $program->id)->where('id', $taskId)->firstOrFail();

        $data = $request->validate([
            'title' => 'required|string|max:500',
            'objective' => 'nullable|string|max:5000',
            'study_note' => 'nullable|string|max:100000',
            'instructions_text' => 'nullable|string|max:20000',
            'estimated_hours' => 'nullable|numeric|min:0.5|max:24',
            'tools' => 'nullable|string|max:500',
            'difficulty' => 'nullable|string|max:64',
            'submission_requirements' => 'nullable|string|max:5000',
            'pass_mark' => 'nullable|integer|min:0|max:100',
            'requires_supervisor_approval' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $instructions = preg_split('/\r\n|\r|\n/', (string) ($data['instructions_text'] ?? ''));
        $instructions = array_values(array_filter(array_map('trim', $instructions), function ($line) {
            return $line !== '';
        }));

        $task->fill([
            'title' => $data['title'],
            'objective' => $data['objective'] ?? null,
            'study_note' => $data['study_note'] ?? null,
            'instructions_json' => json_encode($instructions),
            'estimated_hours' => $data['estimated_hours'] ?? $task->estimated_hours,
            'tools' => $data['tools'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'submission_requirements' => $data['submission_requirements'] ?? null,
            'pass_mark' => $data['pass_mark'] ?? $task->pass_mark,
            'requires_supervisor_approval' => $request->boolean('requires_supervisor_approval'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);
        $task->save();

        return back()->with('message', 'Day '.$task->day_number.' updated.')->with('focus_day', $task->day_number);
    }

    public function importForm()
    {
        $this->allow('internship.programs.import');

        return view('internship.admin.import');
    }

    public function importRun(Request $request)
    {
        $this->allow('internship.programs.import');
        $dry = $request->filled('dry_run');
        $result = $this->service->importCurriculum(null, ! $dry);
        if (! $result['ok']) {
            return back()->with('not_permitted', implode(' ', $result['errors']));
        }
        $msg = $dry
            ? 'Validation OK — '.$result['dry_run'].' programs ready to import.'
            : 'Imported '.$result['imported'].' program(s) successfully.';

        return back()->with('message', $msg);
    }

    public function enrolments()
    {
        $this->allow('internship.enrolments.view');
        $enrolments = InternshipEnrolment::with(['student', 'supervisor', 'program'])
            ->orderByDesc('id')->paginate(40);

        return view('internship.admin.enrolments', compact('enrolments'));
    }

    public function enrolCreate()
    {
        $this->allow('internship.enrolments.create');
        $programs = InternshipProgram::where('status', 'published')->where('is_active', true)->orderBy('name')->get();
        $students = User::where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'phone']);
        $supervisors = User::where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'email']);
        $applications = Application::whereIn('status', [
            Application::STATUS_SELECTED,
            Application::STATUS_HIRED,
            Application::STATUS_AWAITING,
        ])->orderByDesc('submitted_at')->limit(200)->get(['id', 'full_name', 'email', 'reference_number', 'status']);

        return view('internship.admin.enrol_create', compact('programs', 'students', 'supervisors', 'applications'));
    }

    public function enrolStore(Request $request)
    {
        $this->allow('internship.enrolments.create');
        $data = $request->validate([
            'student_user_id' => 'required|integer|exists:users,id',
            'program_id' => 'required|integer|exists:internship_programs,id',
            'supervisor_id' => 'nullable|integer|exists:users,id',
            'application_id' => 'nullable|string|max:64',
            'start_date' => 'nullable|date',
            'start_curriculum_day' => 'required|integer|min:1|max:180',
            'planned_duration_days' => \App\Application::internshipDurationRule(true),
            'notes' => 'nullable|string|max:2000',
        ]);
        try {
            $enrolment = $this->service->enroll($data);
            $this->service->reconcileReleases($enrolment->id);
        } catch (\Throwable $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('internship.enrolments')->with('message', 'Student enrolled. Task will release on their next working day.');
    }

    public function enrolEdit($id)
    {
        $this->allow('internship.enrolments.update');
        $enrolment = InternshipEnrolment::with(['student', 'program', 'supervisor', 'assignments'])->findOrFail($id);
        $supervisors = User::where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'email']);
        $hasAssignments = $enrolment->assignments->isNotEmpty();
        $hasOpen = $enrolment->assignments->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])->isNotEmpty();

        return view('internship.admin.enrol_edit', compact('enrolment', 'supervisors', 'hasAssignments', 'hasOpen'));
    }

    public function enrolUpdate(Request $request, $id)
    {
        $this->allow('internship.enrolments.update');
        $enrolment = InternshipEnrolment::findOrFail($id);
        $data = $request->validate([
            'start_date' => 'nullable|date',
            'start_curriculum_day' => 'nullable|integer|min:1|max:180',
            'next_curriculum_day' => 'nullable|integer|min:1|max:180',
            'planned_duration_days' => \App\Application::internshipDurationRule(false),
            'supervisor_id' => 'nullable|integer|exists:users,id',
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

        return redirect()->route('internship.enrolments')->with('message', 'Placement updated.');
    }

    public function enrolPause($id)
    {
        $this->allow('internship.enrolments.pause');
        $e = InternshipEnrolment::findOrFail($id);
        $e->status = 'paused';
        $e->save();

        return back()->with('message', 'Enrolment paused.');
    }

    public function enrolResume($id)
    {
        $this->allow('internship.enrolments.resume');
        $e = InternshipEnrolment::findOrFail($id);
        $e->status = 'active';
        $e->save();
        $this->service->reconcileReleases($e->id);

        return back()->with('message', 'Enrolment resumed.');
    }

    public function reports()
    {
        $this->allow('internship.reports.view');
        $rows = InternshipEnrolment::with(['student', 'program', 'supervisor'])
            ->orderByDesc('id')->get();

        return view('internship.admin.reports', compact('rows'));
    }
}

<?php

namespace App\Http\Controllers\Internship;

use App\Application;
use App\Http\Controllers\Controller;
use App\InternshipEnrolment;
use App\InternshipProgram;
use App\InternshipProgramTask;
use App\InternshipTaskAssignment;
use App\Services\ApplicationService;
use App\Services\Internship\InternshipProgramService;
use App\Support\InternCompliance;
use App\Support\InternshipHandbook;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'paused_enrolments' => InternshipEnrolment::where('status', 'paused')->count(),
            'completed' => InternshipEnrolment::where('status', 'completed')->count(),
            'pending_review' => DB::table('internship_task_assignments')->where('status', 'submitted')->count(),
            'in_progress_tasks' => DB::table('internship_task_assignments')->where('status', 'in_progress')->count(),
            'available_tasks' => DB::table('internship_task_assignments')->where('status', 'available')->count(),
            'supervisors' => $this->supervisorDirectoryQuery()->count(),
            'interns_ready' => (int) (app(\App\Services\ApplicationService::class)->internshipInternTabCounts()['ready'] ?? 0),
        ];

        $byProgram = InternshipEnrolment::query()
            ->select('program_id', DB::raw('COUNT(*) as total'))
            ->whereIn('status', ['active', 'paused', 'completed'])
            ->groupBy('program_id')
            ->orderByDesc('total')
            ->get();
        $programNames = InternshipProgram::whereIn('id', $byProgram->pluck('program_id')->filter())
            ->get()
            ->keyBy('id');
        $programChart = [
            'labels' => $byProgram->map(function ($row) use ($programNames) {
                $p = $programNames->get($row->program_id);

                return $p ? $p->displayName() : ('Program #'.$row->program_id);
            })->values()->all(),
            'values' => $byProgram->pluck('total')->map(function ($n) {
                return (int) $n;
            })->values()->all(),
        ];

        $taskStatusChart = [
            'labels' => ['Available', 'In progress', 'Submitted', 'Passed'],
            'values' => [
                (int) DB::table('internship_task_assignments')->where('status', 'available')->count(),
                (int) DB::table('internship_task_assignments')->where('status', 'in_progress')->count(),
                (int) DB::table('internship_task_assignments')->where('status', 'submitted')->count(),
                (int) DB::table('internship_task_assignments')->where('status', 'passed')->count(),
            ],
        ];

        $placementChart = [
            'labels' => ['Active', 'Paused', 'Completed'],
            'values' => [
                (int) $stats['active_enrolments'],
                (int) $stats['paused_enrolments'],
                (int) $stats['completed'],
            ],
        ];

        return view('internship.admin.dashboard', compact('stats', 'programChart', 'taskStatusChart', 'placementChart'));
    }

    /**
     * Accepted / hired internship applicants — assign programs and supervisors from here.
     */
    public function interns(Request $request)
    {
        $this->allow('internship.enrolments.view');
        $status = $request->get('status', 'ready');
        $q = $this->acceptedInternsQuery();

        $appService = app(ApplicationService::class);
        if ($status === 'hired') {
            $q->where('applications.status', Application::STATUS_HIRED);
            $appService->applyNeedsAssignment($q);
        } elseif ($status === 'selected') {
            $q->whereIn('applications.status', [Application::STATUS_SELECTED, 'shortlisted']);
            $appService->applyNeedsAssignment($q);
        } elseif ($status === 'placed') {
            $appService->applyHasPlacement($q);
        } elseif ($status === 'ready') {
            $appService->applyNeedsAssignment($q);
        }

        if ($request->filled('q')) {
            $term = '%'.trim($request->get('q')).'%';
            $q->where(function ($w) use ($term) {
                $w->where('applications.full_name', 'like', $term)
                    ->orWhere('applications.email', 'like', $term)
                    ->orWhere('applications.phone', 'like', $term)
                    ->orWhere('applications.whatsapp_number', 'like', $term);
            });
        }

        $interns = $q->orderByDesc('applications.submitted_at')->paginate(40)->appends($request->query());

        $enrolmentsByApp = InternshipEnrolment::with(['student', 'supervisor', 'program'])
            ->whereIn('application_id', $interns->pluck('id')->filter())
            ->whereIn('status', ['active', 'paused', 'completed'])
            ->orderByDesc('id')
            ->get()
            ->keyBy('application_id');

        $unlinked = $interns->filter(function ($app) use ($enrolmentsByApp) {
            return ! isset($enrolmentsByApp[$app->id]);
        });
        if ($unlinked->isNotEmpty()) {
            $emails = $unlinked->pluck('email')->map(function ($e) {
                return strtolower(trim((string) $e));
            })->filter()->unique()->values()->all();
            if ($emails) {
                $placeholders = implode(',', array_fill(0, count($emails), '?'));
                $users = \App\User::where('is_deleted', false)
                    ->whereRaw('LOWER(TRIM(email)) IN ('.$placeholders.')', $emails)
                    ->get()
                    ->keyBy(function ($u) {
                        return strtolower(trim((string) $u->email));
                    });
                $byStudent = InternshipEnrolment::with(['student', 'supervisor', 'program'])
                    ->whereIn('student_user_id', $users->pluck('id')->filter())
                    ->whereIn('status', ['active', 'paused', 'completed'])
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('student_user_id');
                foreach ($unlinked as $app) {
                    $user = $users[strtolower(trim((string) $app->email))] ?? null;
                    if (! $user || ! isset($byStudent[$user->id])) {
                        continue;
                    }
                    $enrolmentsByApp[$app->id] = $byStudent[$user->id]->first();
                }
            }
        }

        // Sync application Working Week → be_working_week for placed interns who never synced.
        foreach ($interns as $app) {
            $enrolment = $enrolmentsByApp[$app->id] ?? null;
            $student = $enrolment ? $enrolment->student : null;
            if (! $student || ! $enrolment) {
                continue;
            }
            if (InternCompliance::workingWeekConfigured($student)) {
                continue;
            }
            if (! $app->hasWorkingWeekOnApplication()) {
                continue;
            }
            try {
                $appService->syncWorkingWeekToUser($student->id, $app->workingWeekData());
            } catch (\Throwable $e) {
                // Leave as "Saved on application" if data is invalid.
            }
        }

        $today = Carbon::today()->toDateString();
        $enrolmentIds = $enrolmentsByApp->pluck('id')->filter()->values()->all();
        // Prefer today's assignment; otherwise the current open task (Selected/Hired alike).
        $todayTasksByEnrolment = collect();
        if ($enrolmentIds) {
            $open = InternshipTaskAssignment::with('task')
                ->whereIn('enrolment_id', $enrolmentIds)
                ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
                ->orderByDesc('scheduled_work_date')
                ->orderByDesc('id')
                ->get()
                ->groupBy('enrolment_id');

            $todayTasksByEnrolment = $open->map(function ($group) use ($today) {
                $forToday = $group->first(function ($a) use ($today) {
                    return optional($a->scheduled_work_date)->toDateString() === $today
                        || (string) $a->scheduled_work_date === $today;
                });

                return $forToday ?: $group->first();
            });
        }

        // Always unfiltered totals — tab clicks clear search so the list matches these badges.
        $tabCounts = $appService->internshipInternTabCounts();

        return view('internship.admin.interns', compact(
            'interns',
            'status',
            'enrolmentsByApp',
            'tabCounts',
            'todayTasksByEnrolment',
            'today'
        ));
    }

    /**
     * Directory of internship supervisors (role + active placement supervisors).
     */
    public function supervisors(Request $request)
    {
        $this->allow('internship.enrolments.view');
        $rows = $this->supervisorDirectoryQuery()->get();

        if ($request->filled('q')) {
            $term = strtolower(trim($request->get('q')));
            $rows = $rows->filter(function ($u) use ($term) {
                return strpos(strtolower((string) $u->name), $term) !== false
                    || strpos(strtolower((string) $u->email), $term) !== false
                    || strpos(preg_replace('/\D/', '', (string) $u->phone), preg_replace('/\D/', '', $term)) !== false;
            })->values();
        }

        $counts = InternshipEnrolment::whereIn('status', ['active', 'paused'])
            ->whereNotNull('supervisor_id')
            ->select('supervisor_id', DB::raw('COUNT(*) as c'))
            ->groupBy('supervisor_id')
            ->pluck('c', 'supervisor_id');

        return view('internship.admin.supervisors', compact('rows', 'counts'));
    }

    protected function acceptedInternsQuery()
    {
        return Application::query()
            ->with(['job', 'internshipProgram'])
            ->whereIn('applications.status', [
                Application::STATUS_SELECTED,
                Application::STATUS_HIRED,
                'shortlisted',
            ])
            ->where(function ($q) {
                $q->whereHas('job', function ($j) {
                    $j->where('posting_type', 'internship');
                })->orWhereNotNull('applications.internship_program_id');
            });
    }

    protected function supervisorDirectoryQuery()
    {
        $roleIds = Role::where('guard_name', 'web')
            ->where(function ($q) {
                $q->where('name', 'Internship Supervisor')
                    ->orWhere('name', 'like', '%Internship Supervisor%');
            })
            ->pluck('id');

        $fromRole = User::query()
            ->where('is_deleted', false)
            ->where('is_active', 1)
            ->whereIn('role_id', $roleIds)
            ->pluck('id');

        $fromEnrolments = InternshipEnrolment::whereIn('status', ['active', 'paused', 'completed'])
            ->whereNotNull('supervisor_id')
            ->pluck('supervisor_id');

        $ids = $fromRole->merge($fromEnrolments)->unique()->filter()->values()->all();

        return User::query()
            ->where('is_deleted', false)
            ->whereIn('id', $ids ?: [0])
            ->orderBy('name');
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
        $handbookDays = [];
        foreach ($program->tasks as $task) {
            if (InternshipHandbook::absolutePath($program, $task)) {
                $handbookDays[(int) $task->day_number] = true;
            }
        }

        return view('internship.admin.program_show', compact('program', 'canEdit', 'handbookDays'));
    }

    public function downloadTaskHandbook($id, $taskId)
    {
        $this->allow('internship.programs.view');
        $program = InternshipProgram::findOrFail($id);
        $task = InternshipProgramTask::where('program_id', $program->id)->where('id', $taskId)->firstOrFail();
        $path = InternshipHandbook::absolutePath($program, $task);
        if (! $path) {
            abort(404, 'Handbook file not found for this day.');
        }

        return response()->download($path, InternshipHandbook::downloadName($program, $task));
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
            'max_students' => 'nullable|integer|min:1|max:10000',
            'is_active' => 'nullable|boolean',
        ]);
        $program->fill([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'discipline' => $data['discipline'] ?? null,
            'prerequisites' => $data['prerequisites'] ?? null,
            'max_students' => $request->filled('max_students') ? (int) $data['max_students'] : null,
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
            'evidence_slots_text' => 'nullable|string|max:4000',
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
        if (Schema::hasColumn('internship_program_tasks', 'evidence_slots_json')) {
            $slots = $task->parseSlotLines($data['evidence_slots_text'] ?? '');
            $task->evidence_slots_json = $slots ? json_encode($slots) : null;
        }
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

    /**
     * Internship Task Manager — open/released daily tasks, checklist progress, resend WhatsApp.
     */
    public function taskManager(Request $request)
    {
        if (Auth::user()->role_id > 2
            && ! in_array('internship.enrolments.view', $this->all_permission, true)
            && ! in_array('internship.supervise', $this->all_permission, true)
            && ! in_array('internship.submissions.grade', $this->all_permission, true)
            && ! in_array('internship.dashboard.view', $this->all_permission, true)) {
            abort(403, 'Internship access denied.');
        }

        $status = $request->get('status', 'open');
        $missingWw = in_array((string) $request->get('missing_ww'), ['1', 'true', 'yes'], true);
        $tomorrow = \Carbon\Carbon::tomorrow()->startOfDay();
        $dayAfter = \Carbon\Carbon::tomorrow()->addDay()->startOfDay();
        $upcoming = collect();
        $assignments = null;
        $missingWeekRows = collect();
        $wwLabels = [];

        // Filter chip: active interns who cannot receive day tasks yet.
        if ($missingWw) {
            $eq = InternshipEnrolment::with(['student', 'program', 'supervisor'])
                ->where('status', 'active');
            if (InternCompliance::shouldScopeSupervisees(Auth::user())) {
                $uid = (int) Auth::id();
                $eq->where(function ($w) use ($uid) {
                    $w->where('supervisor_id', $uid)
                        ->orWhere('supervisors_json', 'like', '%user:'.$uid.'%');
                });
            }
            if ($request->filled('q')) {
                $term = '%'.trim($request->get('q')).'%';
                $eq->whereHas('student', function ($w) use ($term) {
                    $w->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            }
            $missingWeekRows = $eq->get()->filter(function ($e) {
                $student = $e->student;

                return $student && ! InternCompliance::workingWeekConfigured($student);
            })->sortBy(function ($e) {
                return optional($e->student)->name ?: 'zzz';
            })->values();

            $targetDate = null;

            return view('internship.admin.task_manager', compact(
                'assignments', 'status', 'upcoming', 'targetDate', 'missingWw', 'missingWeekRows', 'wwLabels'
            ));
        }

        // Upcoming preview tabs (not yet released, or already scheduled for that date).
        if (in_array($status, ['tomorrow', 'day_after'], true)) {
            $target = $status === 'tomorrow' ? $tomorrow : $dayAfter;
            $upcoming = $this->service->previewUpcomingReleases($target);
            if (InternCompliance::shouldScopeSupervisees(Auth::user())) {
                $uid = (int) Auth::id();
                $upcoming = $upcoming->filter(function ($row) use ($uid) {
                    $enrolment = $row['enrolment'] ?? null;

                    return $enrolment && $enrolment->isSupervisedBy($uid);
                })->values();
            }

            // Also include assignments already created for that calendar day.
            $existingQ = InternshipTaskAssignment::with(['task', 'enrolment.student', 'enrolment.program', 'enrolment.supervisor'])
                ->whereDate('scheduled_work_date', $target->toDateString())
                ->orderBy('progression_day');
            if (InternCompliance::shouldScopeSupervisees(Auth::user())) {
                $uid = (int) Auth::id();
                $existingQ->whereHas('enrolment', function ($w) use ($uid) {
                    $w->where('supervisor_id', $uid)
                        ->orWhere('supervisors_json', 'like', '%user:'.$uid.'%');
                });
            }
            if ($request->filled('q')) {
                $needle = strtolower(trim($request->get('q')));
                $term = '%'.$needle.'%';
                $existingQ->whereHas('enrolment.student', function ($w) use ($term) {
                    $w->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
                $upcoming = $upcoming->filter(function ($row) use ($needle) {
                    $name = strtolower((string) optional($row['student'])->name);
                    $email = strtolower((string) optional($row['student'])->email);

                    return strpos($name, $needle) !== false || strpos($email, $needle) !== false;
                })->values();
            }

            $existing = $existingQ->get();
            $seenEnrolmentDays = [];
            foreach ($existing as $a) {
                $key = $a->enrolment_id.':'.$a->progression_day;
                $seenEnrolmentDays[$key] = true;
                $upcoming->push([
                    'enrolment' => $a->enrolment,
                    'student' => optional($a->enrolment)->student,
                    'program' => optional($a->enrolment)->program,
                    'supervisor' => optional($a->enrolment)->supervisor,
                    'task' => $a->task,
                    'progression_day' => $a->progression_day,
                    'scheduled_work_date' => optional($a->scheduled_work_date)->toDateString() ?: $target->toDateString(),
                    'source' => 'assigned',
                    'assignment' => $a,
                ]);
            }
            $upcoming = $upcoming->filter(function ($row) use ($seenEnrolmentDays) {
                if (($row['source'] ?? '') === 'assigned') {
                    return true;
                }
                $key = optional($row['enrolment'])->id.':'.($row['progression_day'] ?? '');

                return empty($seenEnrolmentDays[$key]);
            })->sortBy(function ($row) {
                return (optional($row['student'])->name ?: 'zzz').'|'.($row['progression_day'] ?? 0);
            })->values();

            $studentIds = $upcoming->map(function ($row) {
                return optional($row['student'])->id;
            })->filter()->unique()->values();
            foreach (User::whereIn('id', $studentIds)->get() as $u) {
                $wwLabels[$u->id] = InternCompliance::workingWeekLabel($u);
            }

            $targetDate = $target->toDateString();

            return view('internship.admin.task_manager', compact(
                'assignments', 'status', 'upcoming', 'targetDate', 'missingWw', 'missingWeekRows', 'wwLabels'
            ));
        }

        $q = InternshipTaskAssignment::with(['task', 'enrolment.student', 'enrolment.program', 'enrolment.supervisor'])
            ->orderByDesc('scheduled_work_date')
            ->orderByDesc('id');

        // Supervisors (non-admin) only see their assigned students.
        if (InternCompliance::shouldScopeSupervisees(Auth::user())) {
            $uid = (int) Auth::id();
            $q->whereHas('enrolment', function ($w) use ($uid) {
                $w->where('supervisor_id', $uid)
                    ->orWhere('supervisors_json', 'like', '%user:'.$uid.'%');
            });
        }

        // Deep-link from Interns "View" — show that assignment even if the name search would miss it.
        if ($request->filled('assignment')) {
            $q->where('id', (int) $request->get('assignment'));
        } elseif ($status === 'open') {
            $q->whereIn('status', ['available', 'in_progress', 'revision_required', 'submitted']);
        } elseif ($status === 'today') {
            $q->whereDate('scheduled_work_date', now()->toDateString());
        } elseif ($status === 'assigned') {
            // Released day tasks only (one row per student assignment — not the full curriculum).
            $q->whereNotNull('released_at')
                ->whereIn('status', ['available', 'in_progress', 'revision_required', 'submitted', 'passed', 'skipped']);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }

        if ($request->filled('q') && ! $request->filled('assignment')) {
            $term = '%'.trim($request->get('q')).'%';
            $q->where(function ($outer) use ($term) {
                $outer->whereHas('enrolment.student', function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                })->orWhereExists(function ($sub) use ($term) {
                    $sub->select(DB::raw(1))
                        ->from('internship_enrolments')
                        ->join('applications', 'applications.id', '=', 'internship_enrolments.application_id')
                        ->whereColumn('internship_enrolments.id', 'internship_task_assignments.enrolment_id')
                        ->where(function ($a) use ($term) {
                            $a->where('applications.full_name', 'like', $term)
                                ->orWhere('applications.email', 'like', $term)
                                ->orWhere('applications.phone', 'like', $term)
                                ->orWhere('applications.whatsapp_number', 'like', $term);
                        });
                })->orWhereHas('task', function ($w) use ($term) {
                    $w->where('title', 'like', $term);
                });
            });
        }

        $assignments = $q->paginate(40)->appends($request->query());
        $studentIds = $assignments->getCollection()->map(function ($a) {
            return optional($a->enrolment)->student_user_id;
        })->filter()->unique()->values();
        foreach (User::whereIn('id', $studentIds)->get() as $u) {
            $wwLabels[$u->id] = InternCompliance::workingWeekLabel($u);
        }
        $targetDate = null;

        return view('internship.admin.task_manager', compact(
            'assignments', 'status', 'upcoming', 'targetDate', 'missingWw', 'missingWeekRows', 'wwLabels'
        ));
    }

    public function resendTask(Request $request, $id)
    {
        if (Auth::user()->role_id > 2
            && ! in_array('internship.notifications.retry', $this->all_permission, true)
            && ! in_array('internship.enrolments.update', $this->all_permission, true)) {
            abort(403, 'Not allowed to resend internship tasks.');
        }

        $assignment = InternshipTaskAssignment::with(['task', 'enrolment.student', 'enrolment.program'])->findOrFail($id);
        $includeSupervisors = $request->boolean('include_supervisors', true);
        $result = $this->service->resendTaskReleased($assignment, $includeSupervisors);

        if (empty($result['success'])) {
            return back()->with('not_permitted', $result['error'] ?? 'Failed to send WhatsApp task / Word handbook.');
        }

        $name = optional(optional($assignment->enrolment)->student)->name ?: 'student';
        $extra = $includeSupervisors
            ? ' Text + Word handbook sent to student; supervisor copy queued with rate-limit pauses.'
            : ' Text + Word handbook sent to student.';

        return back()->with('message', 'Task WhatsApp sent to '.$name.'.'.$extra);
    }

    /**
     * Send (or re-send) the Working Week setup link to an intern via WhatsApp.
     * $id is an application UUID from Interns, or an enrolment id from Task Manager.
     */
    public function requestWorkingWeek(Request $request, $id)
    {
        if (Auth::user()->role_id > 2
            && ! in_array('internship.enrolments.update', $this->all_permission, true)
            && ! in_array('internship.notifications.retry', $this->all_permission, true)) {
            abort(403, 'Not allowed to request a working week.');
        }

        $user = null;
        $name = null;
        $phone = null;

        if (ctype_digit((string) $id)) {
            $enrolment = InternshipEnrolment::with('student')->findOrFail((int) $id);
            $user = $enrolment->student;
            $name = $user ? $user->name : null;
            $phone = $user ? ($user->phone ?: $user->additional_phone) : null;
        } else {
            $application = Application::findOrFail($id);
            $name = $application->full_name;
            $phone = $application->whatsapp_number ?: $application->phone;
            $enrolment = InternshipEnrolment::with('student')
                ->where('application_id', $application->id)
                ->whereIn('status', ['active', 'paused', 'completed'])
                ->orderByDesc('id')
                ->first();
            if (! $enrolment && $application->email) {
                $erp = \App\User::where('is_deleted', false)
                    ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($application->email))])
                    ->first();
                if ($erp) {
                    $enrolment = InternshipEnrolment::with('student')
                        ->where('student_user_id', $erp->id)
                        ->whereIn('status', ['active', 'paused', 'completed'])
                        ->orderByDesc('id')
                        ->first();
                }
            }
            $user = $enrolment ? $enrolment->student : null;
            if ($user) {
                $name = $user->name ?: $name;
                $phone = $user->phone ?: $phone;
            }
        }

        $result = $this->service->requestWorkingWeekSetup($user, $name, $phone);
        $weekUrl = $result['url'] ?? url('/admin/timesheet/working-week');

        if (empty($result['success'])) {
            return back()->with('not_permitted', ($result['error'] ?? 'Could not send WhatsApp.').' Working Week link: '.$weekUrl);
        }

        return back()->with('message', 'Working Week request sent to '.($name ?: 'the intern').'. Link: '.$weekUrl);
    }

    public function showWorkingWeek($id)
    {
        $this->allow('internship.enrolments.view');
        $application = Application::findOrFail($id);
        $enrolment = InternshipEnrolment::with('student')
            ->where('application_id', $application->id)
            ->orderByDesc('id')
            ->first();
        $student = $enrolment ? $enrolment->student : null;
        if (! $student && $application->email) {
            $student = User::whereRaw('LOWER(email) = ?', [strtolower(trim($application->email))])->first();
        }
        $inspect = InternCompliance::workingWeekInspect(
            $student,
            $application->hasWorkingWeekOnApplication() ? $application->workingWeekData() : null
        );
        $backUrl = route('internship.interns');
        $personName = $student ? $student->name : $application->full_name;

        return view('internship.shared.working_week', compact(
            'inspect', 'student', 'application', 'enrolment', 'backUrl', 'personName'
        ));
    }

    public function destroyIntern($id)
    {
        if (! InternCompliance::isInternshipAdmin(Auth::user())) {
            abort(403, 'Only internship admins can delete an intern from the system.');
        }
        $application = Application::findOrFail($id);
        $label = $application->full_name ?: 'Intern';
        try {
            app(\App\Services\Internship\InternAccountPurge::class)->purgeApplication($application, Auth::user());
        } catch (\Throwable $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('internship.interns')
            ->with('message', $label.' was deleted from the system.');
    }
}

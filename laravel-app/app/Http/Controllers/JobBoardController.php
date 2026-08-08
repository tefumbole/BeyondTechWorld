<?php

namespace App\Http\Controllers;

use App\Application;
use App\Customer;
use App\InternshipEnrolment;
use App\JobPosting;
use App\Services\ApplicationService;
use App\Services\Internship\InternshipProgramService;
use App\Services\JobService;
use App\Services\PeopleDirectoryService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class JobBoardController extends Controller
{
    protected $jobs;
    protected $applications;
    protected $all_permission = [];

    public function __construct(JobService $jobs, ApplicationService $applications)
    {
        $this->jobs = $jobs;
        $this->applications = $applications;
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function authorizeJobs()
    {
        if (in_array('jobs_module', $this->all_permission, true)
            || in_array('jobs.view', $this->all_permission, true)
            || in_array('jobs.manage', $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Job Board.');
    }

    public function index(Request $request)
    {
        $this->authorizeJobs();
        $items = $this->jobs->allJobs($request->get('q'), $request->get('status', 'all'));

        return view('job_board.index', [
            'items' => $items,
            'q' => $request->get('q'),
            'status' => $request->get('status', 'all'),
            'jbTab' => 'jobs.index',
        ]);
    }

    public function create()
    {
        $this->authorizeJobs();

        return view('job_board.form', [
            'job' => null,
            'postingType' => old('posting_type', 'job'),
            'internshipPrograms' => $this->publishedInternshipPrograms(),
            'jbTab' => 'jobs.create',
        ]);
    }

    public function createInternship()
    {
        $this->authorizeJobs();

        return view('job_board.form', [
            'job' => null,
            'postingType' => 'internship',
            'internshipPrograms' => $this->publishedInternshipPrograms(),
            'jbTab' => 'jobs.createInternship',
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeJobs();
        $data = $this->validatedJob($request);
        $this->jobs->store($data);
        $label = ($data['posting_type'] ?? 'job') === 'internship' ? 'Internship' : 'Job';

        return redirect()->route('jobs.index')->with('message', $label.' posting created.');
    }

    public function edit($id)
    {
        $this->authorizeJobs();
        $job = JobPosting::findOrFail($id);

        return view('job_board.form', [
            'job' => $job,
            'postingType' => $job->posting_type ?: 'job',
            'internshipPrograms' => $this->publishedInternshipPrograms(),
            'jbTab' => 'jobs.index',
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeJobs();
        $job = JobPosting::findOrFail($id);
        $this->jobs->update($job, $this->validatedJob($request));

        return redirect()->route('jobs.index')->with('message', 'Posting updated.');
    }

    public function clone($id)
    {
        $this->authorizeJobs();
        $job = JobPosting::findOrFail($id);
        $copy = $this->jobs->clone($job);

        return redirect()->route('jobs.edit', $copy->id)->with('message', 'Posting cloned. Review and activate when ready.');
    }

    public function destroy($id)
    {
        $this->authorizeJobs();
        $job = JobPosting::findOrFail($id);
        $this->jobs->delete($job);

        return back()->with('message', 'Job posting deleted.');
    }

    public function applications(Request $request)
    {
        $this->authorizeJobs();
        $status = $request->get('status', 'all');
        $jbTab = $request->get('tab', 'jobs.applications');

        $items = $this->applications->adminList(
            $request->get('job_id', 'all'),
            $status,
            $request->get('q')
        );
        $jobs = JobPosting::orderBy('title')->get(['id', 'title', 'posting_type']);

        return view('job_board.applications', [
            'items' => $items,
            'jobs' => $jobs,
            'jobId' => $request->get('job_id', 'all'),
            'status' => $status,
            'q' => $request->get('q'),
            'jbTab' => $jbTab,
            'pageTitle' => $this->applicationsTitle($status),
            'showStatusFilter' => $jbTab === 'jobs.applications',
        ]);
    }

    public function applicants(Request $request)
    {
        $this->authorizeJobs();
        $q = $request->get('q');
        $people = $this->applications->applicantDirectory($q);
        $programs = $this->publishedInternshipPrograms();
        $directoryPeople = app(\App\Services\PeopleDirectoryService::class)->eligibleForTasks('all', '');
        // Preload Users + Customers only for supervisor picker.
        $directoryPeople = $directoryPeople->filter(function ($p) {
            $source = strtolower((string) ($p['source'] ?? ''));
            $role = strtolower((string) ($p['role'] ?? ''));

            return in_array($source, ['user', 'customer', 'portal'], true)
                || in_array($role, ['staff', 'customer', 'client', 'admin', 'super_admin'], true);
        })->values();

        $openId = (string) $request->get('open', '');
        $application = null;
        $enrolment = null;
        if ($openId !== '') {
            $application = Application::with(['job', 'internshipProgram'])->find($openId);
            if ($application) {
                $enrolment = $this->findEnrolmentForApplication($application);
            } else {
                $openId = '';
            }
        }

        return view('job_board.applicants', [
            'people' => $people,
            'q' => $q,
            'openId' => $openId,
            'application' => $application,
            'enrolment' => $enrolment,
            'jbTab' => 'jobs.applicants',
            'pageTitle' => 'Interns',
            'internshipPrograms' => $programs,
            'directoryPeople' => $directoryPeople,
            'directorySearchUrl' => route('jobs.people.search'),
            'quickSupervisorUrl' => route('jobs.supervisors.quick'),
        ]);
    }

    /**
     * AJAX search for supervisor picker (Users + Customers).
     */
    public function searchPeople(Request $request)
    {
        $this->authorizeJobs();
        $filter = $request->get('filter', 'all');
        if (! in_array($filter, ['all', 'staff', 'customers'], true)) {
            $filter = 'all';
        }
        $rows = app(PeopleDirectoryService::class)
            ->eligibleForTasks($filter, $request->get('q', ''))
            ->filter(function ($p) {
                $source = strtolower((string) ($p['source'] ?? ''));

                return $source !== 'applicant';
            })
            ->values();

        return response()->json($rows);
    }

    /**
     * Create (or reuse) a POS Customer as a supervisor — available system-wide under Customers.
     */
    public function quickSupervisor(Request $request)
    {
        $this->authorizeJobs();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        try {
            $result = app(PeopleDirectoryService::class)->findOrCreateCustomerQuick($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'customer_id' => $result['customer']->id,
            'user' => $result['person'],
        ]);
    }

    /**
     * Send signed Internship Acceptance letters (WhatsApp PDF) to selected interns.
     */
    public function notifyInterns(Request $request)
    {
        $this->authorizeJobs();
        $data = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'required|string',
        ]);

        $result = app(\App\Services\InternshipAcceptanceLetterService::class)
            ->notifyApplications($data['application_ids']);

        $msg = 'Sent '.$result['sent'].' internship acceptance letter(s).';
        if ($result['skipped'] > 0) {
            $msg .= ' '.$result['skipped'].' skipped.';
        }
        $redirect = redirect()->route('jobs.applicants')->with('message', $msg);
        if (! empty($result['errors'])) {
            $redirect->with('not_permitted', implode(' ', array_slice($result['errors'], 0, 5)));
        }
        if (! empty($result['template_id'])) {
            $redirect->with('message', $msg.' Template: Letters → Templates → Internship Acceptance Letter (editable).');
        }

        return $redirect;
    }

    /**
     * Assign selected applicants to an internship program + supervisor + period.
     */
    public function assignInternship(Request $request)
    {
        $this->authorizeJobs();
        $data = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'required|string',
            'program_id' => 'required|integer|exists:internship_programs,id',
            'supervisor_ids' => 'nullable|array',
            'supervisor_ids.*' => 'string|max:80',
            'planned_duration_days' => Application::internshipDurationRule(true),
            'start_curriculum_day' => 'required|integer|min:1|max:180',
            'start_date' => 'required|date',
            'mark_selected' => 'nullable|boolean',
        ]);

        $result = $this->applications->assignApplicantsToInternship($data['application_ids'], [
            'program_id' => $data['program_id'],
            'supervisor_refs' => $data['supervisor_ids'] ?? [],
            'planned_duration_days' => $data['planned_duration_days'],
            'start_curriculum_day' => $data['start_curriculum_day'],
            'start_date' => $data['start_date'],
            'mark_selected' => $request->boolean('mark_selected'),
        ]);

        $msg = 'Assigned '.$result['assigned'].' intern(s) to the internship program.';
        if (! empty($result['supervisors_notified'])) {
            $msg .= ' Notified '.$result['supervisors_notified'].' supervisor(s).';
        }
        if ($result['skipped'] > 0) {
            $msg .= ' '.$result['skipped'].' skipped.';
        }
        if (! empty($result['errors'])) {
            $detail = implode(' ', array_slice($result['errors'], 0, 5));

            return redirect()->route('jobs.applicants')
                ->with('message', $msg)
                ->with('not_permitted', $detail);
        }

        return redirect()->route('jobs.applicants')->with('message', $msg);
    }

    /**
     * Edit one applicant's internship placement (program, supervisors, dates, duration).
     */
    public function editApplicantPlacement($id)
    {
        $this->authorizeJobs();
        $application = Application::with(['job', 'internshipProgram'])->findOrFail($id);
        $enrolment = $this->findEnrolmentForApplication($application);
        $programs = $this->publishedInternshipPrograms();
        $directoryPeople = app(PeopleDirectoryService::class)->eligibleForTasks('all', '')
            ->filter(function ($p) {
                return strtolower((string) ($p['source'] ?? '')) !== 'applicant';
            })->values();

        $selectedSupervisorIds = [];
        if ($enrolment) {
            $selectedSupervisorIds = $enrolment->supervisorRefs();
            if (empty($selectedSupervisorIds) && $enrolment->supervisor_id) {
                $selectedSupervisorIds = ['user:'.$enrolment->supervisor_id];
            }
        }

        $hasAssignments = $enrolment ? $enrolment->assignments()->exists() : false;
        $hasOpen = $enrolment
            ? $enrolment->assignments()
                ->whereIn('status', ['available', 'in_progress', 'submitted', 'revision_required'])
                ->exists()
            : false;

        return view('job_board.applicant_placement_edit', [
            'application' => $application,
            'enrolment' => $enrolment,
            'internshipPrograms' => $programs,
            'directoryPeople' => $directoryPeople,
            'directorySearchUrl' => route('jobs.people.search'),
            'quickSupervisorUrl' => route('jobs.supervisors.quick'),
            'selectedSupervisorIds' => $selectedSupervisorIds,
            'hasAssignments' => $hasAssignments,
            'hasOpen' => $hasOpen,
            'jbTab' => 'jobs.applicants',
        ]);
    }

    public function updateApplicantPlacement(Request $request, $id)
    {
        $this->authorizeJobs();
        $application = Application::findOrFail($id);
        $data = $request->validate([
            'program_id' => 'required|integer|exists:internship_programs,id',
            'supervisor_ids' => 'nullable|array',
            'supervisor_ids.*' => 'string|max:80',
            'planned_duration_days' => Application::internshipDurationRule(true),
            'start_curriculum_day' => 'required|integer|min:1|max:180',
            'start_date' => 'required|date',
            'next_curriculum_day' => 'nullable|integer|min:1|max:180',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $enrolment = $this->findEnrolmentForApplication($application);
            $bundle = $this->applications->resolveSupervisorSelection($data['supervisor_ids'] ?? []);

            $application->internship_program_id = (int) $data['program_id'];
            $application->internship_duration_days = (int) $data['planned_duration_days'];
            $application->save();

            if ($enrolment) {
                $service = app(InternshipProgramService::class);
                $hasAssignments = $enrolment->assignments()->exists();
                if (! $hasAssignments && (int) $enrolment->program_id !== (int) $data['program_id']) {
                    $enrolment->program_id = (int) $data['program_id'];
                    $enrolment->save();
                }
                $update = [
                    'start_date' => $data['start_date'],
                    'supervisor_id' => $bundle['primary_user_id'],
                    'supervisor_refs' => $bundle['refs'],
                    'notes' => $data['notes'] ?? $enrolment->notes,
                ];
                if (! $hasAssignments) {
                    $update['start_curriculum_day'] = (int) $data['start_curriculum_day'];
                    $update['planned_duration_days'] = (int) $data['planned_duration_days'];
                } elseif (! empty($data['next_curriculum_day'])) {
                    $update['next_curriculum_day'] = (int) $data['next_curriculum_day'];
                }
                $service->updatePlacement($enrolment->fresh(), $update);
                if ($enrolment->fresh()->status === 'active') {
                    $service->reconcileReleases($enrolment->id);
                }
            } else {
                $created = $this->applications->assignApplicationToInternship($application, [
                    'program_id' => (int) $data['program_id'],
                    'supervisor_refs' => $data['supervisor_ids'] ?? [],
                    'planned_duration_days' => (int) $data['planned_duration_days'],
                    'start_curriculum_day' => (int) $data['start_curriculum_day'],
                    'start_date' => $data['start_date'],
                    'mark_selected' => false,
                ]);
                if (! $created) {
                    return back()->withInput()->with('not_permitted', 'Could not create internship enrolment for this applicant.');
                }
            }

            if (! empty($bundle['refs'])) {
                $program = \App\InternshipProgram::find((int) $data['program_id']);
                $programName = $program
                    ? (method_exists($program, 'displayName') ? $program->displayName() : $program->name)
                    : 'Internship Programme';
                $days = (int) $data['planned_duration_days'];
                app(\App\Services\ApplicationNotifier::class)->notifySupervisorsAssigned(
                    $bundle['refs'],
                    [$application->full_name],
                    $programName,
                    \Carbon\Carbon::parse($data['start_date'])->format('F j, Y'),
                    $days.' day'.($days === 1 ? '' : 's')
                );
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('jobs.applicants', ['open' => $application->id])
            ->with('message', 'Placement updated for '.$application->full_name.'. Supervisors notified when phone numbers are available.');
    }

    protected function findEnrolmentForApplication(Application $application)
    {
        $enrolment = InternshipEnrolment::with(['program', 'supervisor', 'student'])
            ->where('application_id', $application->id)
            ->orderByDesc('id')
            ->first();
        if ($enrolment) {
            return $enrolment;
        }

        $email = strtolower(trim((string) $application->email));
        if ($email === '') {
            return null;
        }
        $user = User::where('is_deleted', false)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
        if (! $user) {
            return null;
        }

        $q = InternshipEnrolment::with(['program', 'supervisor', 'student'])
            ->where('student_user_id', $user->id)
            ->whereIn('status', ['pending', 'active', 'paused']);
        if ($application->internship_program_id) {
            $q->where('program_id', $application->internship_program_id);
        }

        return $q->orderByDesc('id')->first()
            ?: InternshipEnrolment::with(['program', 'supervisor', 'student'])
                ->where('student_user_id', $user->id)
                ->orderByDesc('id')
                ->first();
    }

    public function awaiting(Request $request)
    {
        $request->merge(['status' => Application::STATUS_AWAITING, 'tab' => 'jobs.awaiting']);

        return $this->applications($request);
    }

    public function selected(Request $request)
    {
        $request->merge(['status' => Application::STATUS_SELECTED, 'tab' => 'jobs.selected']);

        return $this->applications($request);
    }

    public function rejected(Request $request)
    {
        $request->merge(['status' => Application::STATUS_REJECTED, 'tab' => 'jobs.rejected']);

        return $this->applications($request);
    }

    public function showApplication($id)
    {
        $this->authorizeJobs();
        $app = Application::with('job')->findOrFail($id);

        return view('job_board.application_show', [
            'app' => $app,
            'jbTab' => 'jobs.applications',
        ]);
    }

    public function document($id, $type)
    {
        $this->authorizeJobs();
        $app = Application::findOrFail($id);
        $map = [
            'cv' => $app->cv_url ?: $app->cv_path,
            'student_id' => $app->student_id_path,
            'student_id_back' => $app->student_id_back_path,
            'letter' => $app->internship_letter_path,
            'selfie' => $app->selfie_path,
        ];
        if (! isset($map[$type]) || ! $map[$type]) {
            abort(404, 'Document not found.');
        }

        $path = $app->absoluteUploadPath($map[$type]);
        if (! $path && $type === 'cv' && $app->cv_path && is_file($app->cv_path)) {
            $path = $app->cv_path;
        }
        if (! $path) {
            // Fallback: try basename under uploads dir
            $base = basename(parse_url($map[$type], PHP_URL_PATH) ?: $map[$type]);
            $try = base_path('public/uploads/applications/'.$base);
            $path = is_file($try) ? $try : null;
        }
        if (! $path) {
            abort(404, 'File missing on server.');
        }

        return Response::file($path);
    }

    public function updateApplication(Request $request, $id)
    {
        $this->authorizeJobs();
        $data = $request->validate([
            'status' => 'required|string|in:awaiting_approval,selected,rejected,hired,new,reviewed,shortlisted,interview,withdrawn',
            'rejection_reason' => 'nullable|string|max:2000',
            'status_reason' => 'nullable|string|max:2000',
            'interview_date' => 'nullable|date',
        ]);
        if (! empty($data['status_reason']) && empty($data['rejection_reason'])) {
            $data['rejection_reason'] = $data['status_reason'];
        }
        $app = Application::findOrFail($id);
        $this->applications->updateStatus($app, $data);

        return back()->with('message', 'Application updated. Candidate notified via WhatsApp when applicable.');
    }

    /**
     * Delete one or many applications from Job Board lists (Awaiting / Selected / etc.).
     */
    public function deleteApplications(Request $request)
    {
        $this->authorizeJobs();

        $ids = $request->input('application_ids', []);
        if (! is_array($ids)) {
            $ids = array_filter(explode(',', (string) $ids));
        }
        $flat = [];
        foreach ($ids as $raw) {
            foreach (preg_split('/\s*,\s*/', (string) $raw) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $flat[] = $id;
                }
            }
        }

        $deleted = $this->applications->deleteApplications($flat);
        if ($deleted < 1) {
            return back()->with('not_permitted', 'No applications were selected or found to delete.');
        }

        return back()->with('message', $deleted === 1
            ? '1 application deleted.'
            : $deleted.' applications deleted.');
    }

    protected function applicationsTitle($status)
    {
        if ($status === Application::STATUS_AWAITING) {
            return 'Awaiting Approval';
        }
        if ($status === Application::STATUS_SELECTED) {
            return 'Selected';
        }
        if ($status === Application::STATUS_REJECTED) {
            return 'Rejected';
        }

        return 'All Applications';
    }

    protected function validatedJob(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'employment_type' => 'nullable|string|max:100',
            'posting_type' => 'required|string|in:job,internship',
            'internship_program_ids' => 'nullable|array',
            'internship_program_ids.*' => 'integer|exists:internship_programs,id',
            'salary' => 'nullable|string|max:100',
            'requirements' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'deadline' => 'nullable|date',
            'max_positions' => 'nullable|integer|min:1',
            'max_applicants' => 'nullable|integer|min:1',
            'expected_applicants' => 'nullable|integer|min:1',
            'enable_countdown' => 'nullable',
            'status' => 'required|string|in:active,open,draft,closed,archived',
        ]);
        $data['enable_countdown'] = $request->has('enable_countdown');
        if (($data['posting_type'] ?? '') === 'internship') {
            $data['salary'] = null;
            if (empty($data['employment_type'])) {
                $data['employment_type'] = 'Internship';
            }
            $ids = $data['internship_program_ids'] ?? [];
            if (empty($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'internship_program_ids' => ['Select at least one internship program for candidates to choose from.'],
                ]);
            }
        } else {
            $data['internship_program_ids'] = [];
        }

        return $data;
    }

    protected function publishedInternshipPrograms()
    {
        if (! class_exists(\App\InternshipProgram::class)) {
            return collect();
        }

        return \App\InternshipProgram::where('status', 'published')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'version']);
    }
}

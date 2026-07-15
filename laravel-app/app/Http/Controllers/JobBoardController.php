<?php

namespace App\Http\Controllers;

use App\Application;
use App\JobPosting;
use App\Services\ApplicationService;
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'postingType' => 'job',
            'jbTab' => 'jobs.create',
        ]);
    }

    public function createInternship()
    {
        $this->authorizeJobs();

        return view('job_board.form', [
            'job' => null,
            'postingType' => 'internship',
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

    public function updateApplication(Request $request, $id)
    {
        $this->authorizeJobs();
        $data = $request->validate([
            'status' => 'required|string|in:awaiting_approval,selected,rejected,hired,new,reviewed,shortlisted,interview,withdrawn',
            'rejection_reason' => 'nullable|string|max:2000',
            'interview_date' => 'nullable|date',
        ]);
        $app = Application::findOrFail($id);
        $this->applications->updateStatus($app, $data);

        return back()->with('message', 'Application updated. Candidate notified via WhatsApp when applicable.');
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
        }

        return $data;
    }
}

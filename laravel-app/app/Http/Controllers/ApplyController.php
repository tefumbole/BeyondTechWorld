<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\ApplicationService;
use App\Services\BeyondWasenderService;
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplyController extends Controller
{
    protected $jobs;
    protected $applications;
    protected $whatsapp;

    public function __construct(JobService $jobs, ApplicationService $applications, BeyondWasenderService $whatsapp)
    {
        $this->jobs = $jobs;
        $this->applications = $applications;
        $this->whatsapp = $whatsapp;
    }

    public function index(Request $request)
    {
        $search = $request->query('q');
        $jobs = $this->jobs->activeJobs($search);

        $stats = [];
        foreach ($jobs as $job) {
            $stats[$job->id] = $this->jobs->stats($job);
        }

        return view('beyond.apply.index', compact('jobs', 'stats', 'search'));
    }

    public function show($id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->route('apply.index')->with('warning', 'That job posting is no longer available.');
        }

        $stats = $this->jobs->stats($job);
        $availability = $this->jobs->availability($job);
        $countryCodes = $this->applications->countryCodes();

        return view('beyond.apply.show', compact('job', 'stats', 'availability', 'countryCodes'));
    }

    public function store(Request $request, $id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->route('apply.index')->with('warning', 'That job posting is no longer available.');
        }

        $availability = $this->jobs->availability($job);
        if (! $availability['available']) {
            return back()->withErrors(['job' => $availability['reason']]);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:50',
            'expected_salary' => 'nullable|string|max:100',
            'availability' => 'required|string|max:50',
            'availability_days' => 'nullable|integer|min:1|max:365',
            'cover_letter' => 'nullable|string|max:5000',
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $validated['country'] = $this->applications->countryName($validated['country_code']);

        $user = Auth::guard('beyond')->user();
        $application = $this->applications->apply($job, $validated, $request->file('cv'), $user ? $user->id : null);

        $this->notifyApplicant($application, $job);

        return redirect()->route('apply.confirmation', $application->reference_number);
    }

    public function confirmation($reference)
    {
        $application = Application::with('job')->where('reference_number', $reference)->first();

        return view('beyond.apply.confirmation', compact('application', 'reference'));
    }

    protected function notifyApplicant(Application $application, $job)
    {
        if (! $application->phone) {
            return;
        }

        $message = "Hello {$application->full_name},\n\n"
            ."Your application for *{$job->title}* at Beyond Enterprise has been received.\n"
            ."Reference: {$application->reference_number}\n\n"
            ."We will review your application and contact you regarding the next steps.";

        $this->whatsapp->sendText($application->phone, $message);
    }
}

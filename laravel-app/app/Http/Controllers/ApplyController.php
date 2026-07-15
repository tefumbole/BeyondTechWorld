<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\ApplicationService;
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplyController extends Controller
{
    protected $jobs;
    protected $applications;

    public function __construct(JobService $jobs, ApplicationService $applications)
    {
        $this->jobs = $jobs;
        $this->applications = $applications;
    }

    public function index(Request $request)
    {
        $search = $request->query('q');
        $all = $this->jobs->activeJobs($search);
        $jobs = $all->filter(function ($job) {
            return ! $job->isInternship();
        })->values();
        $internships = $all->filter(function ($job) {
            return $job->isInternship();
        })->values();

        // If there are only internships (no jobs), show Internships first.
        $internshipsFirst = $jobs->isEmpty() && $internships->isNotEmpty();

        $stats = [];
        foreach ($all as $job) {
            $stats[$job->id] = $this->jobs->stats($job);
        }

        return view('beyond.apply.index', compact('jobs', 'internships', 'stats', 'search', 'internshipsFirst'));
    }

    public function show($id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->route('apply.index')->with('warning', 'That posting is no longer available.');
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
            return redirect()->route('apply.index')->with('warning', 'That posting is no longer available.');
        }

        $availability = $this->jobs->availability($job);
        if (! $availability['available']) {
            return back()->withErrors(['job' => $availability['reason']]);
        }

        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country_code' => 'required|string|max:10',
            'whatsapp_number' => 'required|string|max:50',
            'availability' => 'required|string|max:50',
            'availability_days' => 'nullable|integer|min:1|max:365',
            'cover_letter' => 'nullable|string|max:5000',
        ];

        if ($job->isInternship()) {
            $rules['cv'] = 'nullable|file|mimes:pdf,doc,docx|max:5120';
            $rules['student_id'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:5120';
            $rules['internship_letter'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:5120';
            $rules['selfie'] = 'required|file|mimes:jpeg,jpg,png|max:5120';
            $rules['signature_image'] = 'required|string|max:500000';
            $rules['agreement_accepted'] = 'required|accepted';
        } else {
            $rules['cv'] = 'required|file|mimes:pdf,doc,docx|max:5120';
            $rules['expected_salary'] = 'nullable|string|max:100';
        }

        $validated = $request->validate($rules);
        $validated['country'] = $this->applications->countryName($validated['country_code']);

        $user = Auth::guard('beyond')->user();
        $application = $this->applications->apply(
            $job,
            $validated,
            $request->file('cv'),
            $user ? $user->id : null,
            [
                'student_id' => $request->file('student_id'),
                'internship_letter' => $request->file('internship_letter'),
                'selfie' => $request->file('selfie'),
            ]
        );

        return redirect()->route('apply.confirmation', $application->reference_number);
    }

    public function confirmation($reference)
    {
        $application = Application::with('job')->where('reference_number', $reference)->first();

        return view('beyond.apply.confirmation', compact('application', 'reference'));
    }
}

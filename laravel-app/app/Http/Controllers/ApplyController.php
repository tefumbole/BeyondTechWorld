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

    public function show(Request $request, $id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->route('apply.index')->with('warning', 'That posting is no longer available.');
        }

        if (! in_array($job->status, ['active', 'open'], true)) {
            return redirect()->route('apply.index')->with('warning', 'That posting is currently closed.');
        }

        if ($request->boolean('apply')) {
            return redirect()->route('apply.form', $job->id);
        }

        $stats = $this->jobs->stats($job);
        $availability = $this->jobs->availability($job);

        return view('beyond.apply.show', compact('job', 'stats', 'availability'));
    }

    public function form($id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->route('apply.index')->with('warning', 'That posting is no longer available.');
        }

        if (! in_array($job->status, ['active', 'open'], true)) {
            return redirect()->route('apply.index')->with('warning', 'That posting is currently closed.');
        }

        $availability = $this->jobs->availability($job);
        if (! $availability['available']) {
            return redirect()->route('apply.show', $job->id)
                ->with('warning', $availability['reason'] ?? 'Applications are closed for this posting.');
        }

        $stats = $this->jobs->stats($job);
        $countryCodes = $this->applications->countryCodes();

        return view('beyond.apply.form', compact('job', 'stats', 'availability', 'countryCodes'));
    }

    public function store(Request $request, $id)
    {
        $job = $this->jobs->find($id);
        if (! $job) {
            return redirect()->route('apply.index')->with('warning', 'That posting is no longer available.');
        }

        $availability = $this->jobs->availability($job);
        if (! $availability['available']) {
            return redirect()->route('apply.form', $job->id)
                ->withErrors(['job' => $availability['reason']]);
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
            $allowedProgramIds = $job->internshipPrograms()->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();
            $rules['internship_program_id'] = empty($allowedProgramIds)
                ? 'required|integer|exists:internship_programs,id'
                : 'required|integer|in:'.implode(',', $allowedProgramIds);
            $rules['internship_duration_days'] = \App\Application::internshipDurationRule(true);
            $rules['education_status'] = 'required|in:currently_studying,graduated,not_a_student';
            $rules['is_academic_required'] = 'nullable|in:0,1';
            $rules['cv'] = 'nullable|file|mimes:pdf,doc,docx|max:5120';
            $rules['student_id'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:5120';
            $rules['student_id_back'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:5120';
            $rules['selfie'] = 'required|file|mimes:jpeg,jpg,png|max:5120';
            $rules['signature_image'] = 'required|string|max:500000';
            $rules['agreement_accepted'] = 'required|accepted';
            $rules['defer_internship_letter'] = 'nullable|in:0,1';
            $rules['defer_worker_proof'] = 'nullable|in:0,1';
            $rules['employment_letter'] = 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120';
            $rules['official_badge'] = 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120';
            $rules = array_merge($rules, \App\Support\WorkingWeekForm::validationRules());

            $educationStatus = (string) $request->input('education_status', '');
            $isWorker = $educationStatus === 'not_a_student';
            $rules['school'] = $isWorker ? 'nullable|string|max:255' : 'required|string|max:255';
            $rules['level_of_study'] = $isWorker ? 'nullable|string|max:100' : 'required|string|max:100';

            $deferLetter = (string) $request->input('defer_internship_letter', '0') === '1';
            $deferWorkerProof = (string) $request->input('defer_worker_proof', '0') === '1';
            $academicRequired = ! $isWorker
                && $educationStatus === 'currently_studying'
                && (string) $request->input('is_academic_required', '0') === '1';

            // School letter: required unless deferred (students).
            if (! $isWorker) {
                $rules['internship_letter'] = ($academicRequired && ! $deferLetter)
                    ? 'required|file|mimes:jpeg,jpg,png,pdf|max:5120'
                    : 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120';
            } else {
                $rules['internship_letter'] = 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120';
            }
        } else {
            $rules['cv'] = 'required|file|mimes:pdf,doc,docx|max:5120';
            $rules['expected_salary'] = 'nullable|string|max:100';
        }

        $validated = $request->validate($rules);
        $validated['country'] = $this->applications->countryName($validated['country_code']);
        if ($job->isInternship()) {
            $educationStatus = (string) ($validated['education_status'] ?? '');
            $isWorker = $educationStatus === 'not_a_student';
            $validated['applicant_type'] = $isWorker
                ? \App\Application::APPLICANT_WORKER
                : \App\Application::APPLICANT_STUDENT;
            if ($isWorker || $educationStatus === 'graduated') {
                $validated['is_academic_required'] = 0;
            } else {
                $validated['is_academic_required'] = (string) $request->input('is_academic_required', '0') === '1' ? 1 : 0;
            }

            $deferLetter = (string) $request->input('defer_internship_letter', '0') === '1';
            $deferWorkerProof = (string) $request->input('defer_worker_proof', '0') === '1';
            $validated['defer_internship_letter'] = $deferLetter ? 1 : 0;
            $validated['defer_worker_proof'] = $deferWorkerProof ? 1 : 0;

            if ($isWorker) {
                $hasEmployment = $request->hasFile('employment_letter');
                $hasBadge = $request->hasFile('official_badge');
                if (! $deferWorkerProof && ! $hasEmployment && ! $hasBadge) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'employment_letter' => [
                            'Upload an employment letter or official badge, or choose “Submit later”.',
                        ],
                    ]);
                }
            }

            $wwData = \App\Support\WorkingWeekForm::fromRequest($request);
            \App\Support\WorkingWeekForm::assertValid($wwData, 'working_week');
            $validated['working_week'] = $wwData;
        }

        // Fail with a form error (not a 500) when the WhatsApp number is invalid.
        $this->applications->combinePhone(
            $validated['country_code'],
            $validated['whatsapp_number']
        );

        $user = Auth::guard('beyond')->user();
        try {
            $application = $this->applications->apply(
                $job,
                $validated,
                $request->file('cv'),
                $user ? $user->id : null,
                [
                    'student_id' => $request->file('student_id'),
                    'student_id_back' => $request->file('student_id_back'),
                    'internship_letter' => $request->file('internship_letter'),
                    'employment_letter' => $request->file('employment_letter'),
                    'official_badge' => $request->file('official_badge'),
                    'selfie' => $request->file('selfie'),
                ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Internship/job apply failed: '.$e->getMessage(), [
                'job_id' => $job->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('apply.form', $job->id)
                ->withInput()
                ->withErrors(['job' => 'Could not submit your application. Please check your details and try again.']);
        }

        return redirect()->route('apply.confirmation', $application->reference_number);
    }

    public function confirmation($reference)
    {
        $application = Application::with('job')->where('reference_number', $reference)->first();

        return view('beyond.apply.confirmation', compact('application', 'reference'));
    }
}

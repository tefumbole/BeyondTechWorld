<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\ApplicationService;
use Illuminate\Http\Request;

class ApplicationDocumentsController extends Controller
{
    protected $applications;

    public function __construct(ApplicationService $applications)
    {
        $this->applications = $applications;
    }

    public function show($token)
    {
        $application = Application::with('job')
            ->where('documents_update_token', $token)
            ->firstOrFail();

        $missing = $application->missingDocumentKeys();
        $labels = Application::documentKeyLabels();

        // If complete, still show a thank-you state.
        return view('beyond.apply.documents_update', [
            'application' => $application,
            'missing' => $missing,
            'labels' => $labels,
            'complete' => empty($missing),
        ]);
    }

    public function store(Request $request, $token)
    {
        $application = Application::where('documents_update_token', $token)->firstOrFail();

        $rules = [
            'internship_letter' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'employment_letter' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'official_badge' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'student_id' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'student_id_back' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'selfie' => 'nullable|file|mimes:jpeg,jpg,png|max:5120',
        ];
        $request->validate($rules);

        try {
            $this->applications->submitDocumentUpdate($application, [
                'internship_letter' => $request->file('internship_letter'),
                'employment_letter' => $request->file('employment_letter'),
                'official_badge' => $request->file('official_badge'),
                'student_id' => $request->file('student_id'),
                'student_id_back' => $request->file('student_id_back'),
                'selfie' => $request->file('selfie'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        return redirect()->route('apply.documents', $token)
            ->with('message', 'Documents uploaded successfully. Thank you.');
    }
}

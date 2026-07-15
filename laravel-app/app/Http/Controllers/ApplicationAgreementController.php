<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\ApplicationService;
use Illuminate\Http\Request;

class ApplicationAgreementController extends Controller
{
    protected $applications;

    public function __construct(ApplicationService $applications)
    {
        $this->applications = $applications;
    }

    public function show($token)
    {
        $application = Application::with('job')->where('agreement_token', $token)->firstOrFail();

        if ($application->agreement_signed_at) {
            return view('beyond.apply.agreement_signed', compact('application'));
        }

        if (! in_array($application->status, [Application::STATUS_SELECTED, Application::STATUS_HIRED], true)
            && ! in_array($application->status, ['shortlisted'], true)) {
            return view('beyond.apply.agreement_unavailable', [
                'application' => $application,
                'message' => 'This agreement link is not active yet. You will be notified on WhatsApp when selected.',
            ]);
        }

        return view('beyond.apply.agreement', compact('application'));
    }

    public function sign(Request $request, $token)
    {
        $application = Application::with('job')->where('agreement_token', $token)->firstOrFail();

        if ($application->agreement_signed_at) {
            return redirect()->route('apply.agreement', $token)
                ->with('message', 'This agreement has already been signed.');
        }

        $request->validate([
            'agreement_accepted' => 'required|accepted',
            'agreement_read_confirmed' => 'required',
            'signature_image' => 'required|string|max:500000',
        ]);

        $this->applications->markAgreementSigned($application, $request->signature_image);

        return redirect()->route('apply.agreement', $token)
            ->with('message', 'Agreement signed successfully. You will receive a WhatsApp confirmation.');
    }
}

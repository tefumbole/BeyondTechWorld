<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\ApplicationService;
use App\Support\WorkingWeekForm;
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
                'message' => 'This offer link is not active yet. You will be notified on WhatsApp when selected.',
            ]);
        }

        $job = $application->job;
        if ($job && $job->isInternship() && $application->needsOfferPortal()) {
            $step = (int) session($this->sessionKey($token).'.step', 1);
            $step = max(1, min(4, $step));
            $wwData = session($this->sessionKey($token).'.ww')
                ?: $application->workingWeekData();

            return view('beyond.apply.offer_portal', [
                'application' => $application,
                'step' => $step,
                'wwData' => $wwData,
            ]);
        }

        return view('beyond.apply.agreement', compact('application'));
    }

    public function processOffer(Request $request, $token)
    {
        $application = Application::with('job')->where('agreement_token', $token)->firstOrFail();

        if ($application->agreement_signed_at || ! $application->needsOfferPortal()) {
            return redirect()->route('apply.agreement', $token)
                ->with('message', $application->agreement_signed_at
                    ? 'This offer has already been accepted.'
                    : 'Please use the agreement form on this page.');
        }

        if (! $application->needsOfferPortal()
            || ! $application->job
            || ! $application->job->isInternship()) {
            return redirect()->route('apply.agreement', $token);
        }

        if (! in_array($application->status, [Application::STATUS_SELECTED, Application::STATUS_HIRED, 'shortlisted'], true)) {
            return view('beyond.apply.agreement_unavailable', [
                'application' => $application,
                'message' => 'This offer link is not active yet.',
            ]);
        }

        $step = (int) $request->input('step', 1);
        $key = $this->sessionKey($token);

        if ($step === 1) {
            $request->validate([
                'agreement_accepted' => 'required|accepted',
                'agreement_read_confirmed' => 'required',
            ]);
            session([$key.'.step' => 2, $key.'.terms' => true]);

            return redirect()->route('apply.agreement', $token);
        }

        if ($step === 2) {
            if (! session($key.'.terms')) {
                session([$key.'.step' => 1]);

                return redirect()->route('apply.agreement', $token)
                    ->withErrors(['agreement_accepted' => 'Please accept the offer terms first.']);
            }
            $request->validate([
                'password' => 'required|string|min:8|max:100|confirmed',
            ]);
            session([
                $key.'.step' => 3,
                $key.'.password' => $request->input('password'),
            ]);

            return redirect()->route('apply.agreement', $token);
        }

        if ($step === 3) {
            if (! session($key.'.password')) {
                session([$key.'.step' => 2]);

                return redirect()->route('apply.agreement', $token)
                    ->withErrors(['password' => 'Please create your account password first.']);
            }
            $wwData = WorkingWeekForm::fromRequest($request);
            WorkingWeekForm::assertValid($wwData, 'working_week');
            session([$key.'.step' => 4, $key.'.ww' => $wwData]);
            $application->working_week_json = WorkingWeekForm::toJson($wwData);
            $application->save();

            return redirect()->route('apply.agreement', $token);
        }

        if ($step === 4) {
            if (! session($key.'.terms') || ! session($key.'.password')) {
                session([$key.'.step' => 1]);

                return redirect()->route('apply.agreement', $token);
            }
            $request->validate([
                'signature_image' => 'required|string|max:500000',
            ]);
            $wwData = session($key.'.ww') ?: $application->workingWeekData();
            WorkingWeekForm::assertValid($wwData, 'working_week');
            $password = session($key.'.password');

            $this->applications->completeOfferPortal(
                $application,
                $request->input('signature_image'),
                $password,
                $wwData
            );

            session()->forget($key);

            return redirect()->route('apply.agreement', $token)
                ->with('message', 'Offer accepted. Your account is ready — you will receive a WhatsApp confirmation.');
        }

        return redirect()->route('apply.agreement', $token);
    }

    public function sign(Request $request, $token)
    {
        $application = Application::with('job')->where('agreement_token', $token)->firstOrFail();

        if ($application->needsOfferPortal() && $application->job && $application->job->isInternship()) {
            return $this->processOffer($request, $token);
        }

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

    protected function sessionKey($token)
    {
        return 'offer_portal.'.$token;
    }
}

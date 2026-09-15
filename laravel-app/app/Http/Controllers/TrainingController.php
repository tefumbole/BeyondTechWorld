<?php

namespace App\Http\Controllers;

use App\Services\BeyondWasenderService;
use App\Services\TrainingService;
use App\TrainingRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrainingController extends Controller
{
    protected $training;
    protected $whatsapp;

    public function __construct(TrainingService $training, BeyondWasenderService $whatsapp)
    {
        $this->training = $training;
        $this->whatsapp = $whatsapp;
    }

    public function trainings()
    {
        $manager = app(\App\Services\CourseManagerService::class);
        $programs = $manager->programsForFrontend();
        if (empty($programs)) {
            $programs = $this->trainingProgramsConfig();
        }

        return view('beyond.trainings', [
            'programs' => $programs,
        ]);
    }

    public function registerNow()
    {
        return view('beyond.register-now', [
            'courses' => $this->training->courses(),
            'countryCodes' => \App\Support\CountryDialCodes::all(),
        ]);
    }

    public function storeRegistration(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'company_name' => 'nullable|string|max:255',
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'string',
        ]);

        if (! empty($validated['country_code'])) {
            $validated['client_phone'] = \App\Support\CountryDialCodes::combine(
                $validated['country_code'],
                $validated['client_phone']
            );
        }

        $user = Auth::guard('beyond')->user();
        $registration = $this->training->register($validated, $validated['course_ids'], $user ? $user->id : null);

        $this->notifyRegistration($registration);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'reference_number' => $registration->reference_number,
                'redirect' => route('training.registered', $registration->reference_number),
            ]);
        }

        return redirect()->route('training.registered', $registration->reference_number);
    }

    public function registered($reference)
    {
        $registration = TrainingRegistration::where('reference_number', $reference)->first();

        return view('beyond.registration-confirmation', compact('registration', 'reference'));
    }

    protected function notifyRegistration(TrainingRegistration $registration)
    {
        if (! $registration->client_phone) {
            return;
        }

        $message = \App\Support\WhatsAppMessage::trainingRegistration(
            $registration->client_name,
            $registration->reference_number,
            $registration->course_names
        );

        $this->whatsapp->sendText($registration->client_phone, $message);
    }

    private function trainingProgramsConfig()
    {
        $path = config_path('training-modules.json');
        if (! \Illuminate\Support\Facades\File::exists($path)) {
            return [];
        }

        return json_decode(\Illuminate\Support\Facades\File::get($path), true) ?: [];
    }
}

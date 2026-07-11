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
        return view('beyond.trainings', [
            'programs' => $this->trainingProgramsConfig(),
        ]);
    }

    public function registerNow()
    {
        return view('beyond.register-now', [
            'courses' => $this->training->courses(),
        ]);
    }

    public function storeRegistration(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'course_ids' => 'required|array|min:1',
            'course_ids.*' => 'string',
        ]);

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

        $message = "Hello {$registration->client_name},\n\n"
            ."Your Beyond Enterprise training registration was received.\n"
            ."Reference: {$registration->reference_number}\n"
            ."Courses: {$registration->course_names}\n\n"
            ."Our team will contact you shortly with the next steps.";

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

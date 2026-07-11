<?php

namespace App\Http\Controllers;

use App\Shareholder;
use App\Services\BeyondWasenderService;
use App\Services\ShareholderRegistrationService;
use App\Services\ShareSettingsService;
use Illuminate\Http\Request;

class ShareholderController extends Controller
{
    protected $registration;
    protected $settings;
    protected $whatsapp;

    public function __construct(
        ShareholderRegistrationService $registration,
        ShareSettingsService $settings,
        BeyondWasenderService $whatsapp
    ) {
        $this->registration = $registration;
        $this->settings = $settings;
        $this->whatsapp = $whatsapp;
    }

    public function landing()
    {
        $settings = $this->settings->getSettings();
        $priceLabel = $this->settings->formatPrice($settings['price_per_share'], $settings['currency']);

        return view('beyond.shareholders.landing', compact('settings', 'priceLabel'));
    }

    public function acceptTerms(Request $request)
    {
        $request->session()->put('shareholder_terms_accepted', true);

        return redirect()->route('shareholders.shares');
    }

    public function shares(Request $request)
    {
        if (! $request->session()->get('shareholder_terms_accepted')) {
            return redirect()->route('shareholders.landing')
                ->with('warning', 'Please read and accept the Shareholder Agreement first.');
        }

        $settings = $this->settings->getSettings();
        $priceLabel = $this->settings->formatPrice($settings['price_per_share'], $settings['currency']);
        $countryCodes = $this->registration->countryCodes();

        return view('beyond.shareholders.shares', compact('settings', 'priceLabel', 'countryCodes'));
    }

    public function store(Request $request)
    {
        if (! $request->session()->get('shareholder_terms_accepted')) {
            return redirect()->route('shareholders.landing');
        }

        $available = $this->registration->availableShares();

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country_code' => 'required|string|max:10',
            'phone_number' => 'required|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:2000',
            'nationality' => 'nullable|string|max:100',
            'shares_count' => 'required|integer|min:1|max:'.$available,
            'terms_accepted' => 'accepted',
            'signature' => 'required|string',
        ]);

        try {
            $shareholder = $this->registration->register($validated);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['shares_count' => $e->getMessage()]);
        }

        $this->notifyRegistration($shareholder);

        return redirect()->route('shareholders.confirmation', ['reference' => $shareholder->reference_number]);
    }

    public function confirmation($reference)
    {
        $shareholder = Shareholder::where('reference_number', $reference)->first();

        if (! $shareholder) {
            return view('beyond.shareholders.confirmation', ['shareholder' => null, 'reference' => $reference]);
        }

        $settings = $this->settings->getSettings();
        $investmentLabel = $this->settings->formatPrice($shareholder->investment_amount, $settings['currency']);

        return view('beyond.shareholders.confirmation', compact('shareholder', 'reference', 'investmentLabel'));
    }

    public function verify($id)
    {
        $shareholder = Shareholder::find($id);

        if (! $shareholder) {
            return view('beyond.shareholders.verify', ['shareholder' => null, 'error' => 'Agreement not found.']);
        }

        if (! $shareholder->agreement_signed_at) {
            return view('beyond.shareholders.verify', ['shareholder' => null, 'error' => 'This agreement has not been signed yet.']);
        }

        $settings = $this->settings->getSettings();
        $investmentLabel = $this->settings->formatPrice($shareholder->investment_amount, $settings['currency']);

        return view('beyond.shareholders.verify', compact('shareholder', 'investmentLabel'));
    }

    protected function notifyRegistration(Shareholder $shareholder)
    {
        $settings = $this->settings->getSettings();
        $amount = $this->settings->formatPrice($shareholder->investment_amount, $settings['currency']);
        $verifyUrl = url('/verify/agreement/'.$shareholder->id);

        $message = "Hello {$shareholder->full_name},\n\n"
            ."Your Beyond Enterprise shareholder registration was received.\n"
            ."Reference: {$shareholder->reference_number}\n"
            ."Shares: {$shareholder->shares_assigned}\n"
            ."Investment: {$amount}\n\n"
            ."Our team will contact you with payment instructions.\n"
            ."Verify your signed agreement: {$verifyUrl}";

        $this->whatsapp->sendText($shareholder->full_phone_number, $message);
    }
}

<?php

namespace App\Services;

use App\Shareholder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ShareholderRegistrationService
{
    protected $settings;

    public function __construct(ShareSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function generateReferenceNumber()
    {
        do {
            $ref = 'SH-'.date('Y').'-'.date('md').'-'.random_int(10000, 99999);
        } while (Shareholder::where('reference_number', $ref)->exists());

        return $ref;
    }

    public function availableShares()
    {
        return $this->settings->getSettings()['available_shares'];
    }

    public function register(array $data)
    {
        $settings = $this->settings->getSettings();
        $shares = (int) $data['shares_count'];
        $available = $settings['available_shares'];

        if ($shares < 1) {
            throw new \InvalidArgumentException('Number of shares must be at least 1.');
        }
        if ($shares > $available) {
            throw new \InvalidArgumentException("Only {$available} shares are currently available.");
        }

        $fullPhone = $this->combinePhone($data['country_code'], $data['phone_number']);
        $investment = round($shares * $settings['price_per_share'], 2);
        $user = Auth::guard('beyond')->user();

        $shareholder = Shareholder::create([
            'id' => (string) Str::uuid(),
            'full_name' => trim($data['full_name']),
            'name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'phone_number' => preg_replace('/\D/', '', $data['phone_number']),
            'country_code' => $data['country_code'],
            'full_phone_number' => $fullPhone,
            'company_name' => $data['company_name'] ?? null,
            'address' => trim($data['address']),
            'nationality' => $data['nationality'] ?? null,
            'shares_assigned' => $shares,
            'investment_amount' => $investment,
            'signature' => $data['signature'],
            'agreement_signed_at' => now(),
            'status' => 'pending_approval',
            'payment_status' => 'pending',
            'is_guest' => $user ? false : true,
            'user_id' => $user ? $user->id : null,
            'reference_number' => $this->generateReferenceNumber(),
            'submitted_at' => now(),
        ]);

        return $shareholder;
    }

    public function combinePhone($code, $number)
    {
        $digits = preg_replace('/\D/', '', $number);
        $code = trim($code);
        if (strpos($code, '+') !== 0) {
            $code = '+'.$code;
        }

        return $code.$digits;
    }

    public function countryCodes()
    {
        return [
            '+237' => 'Cameroon (+237)',
            '+250' => 'Rwanda (+250)',
            '+256' => 'Uganda (+256)',
            '+254' => 'Kenya (+254)',
            '+255' => 'Tanzania (+255)',
            '+234' => 'Nigeria (+234)',
            '+233' => 'Ghana (+233)',
            '+27' => 'South Africa (+27)',
            '+1' => 'USA/Canada (+1)',
            '+44' => 'UK (+44)',
            '+33' => 'France (+33)',
        ];
    }
}

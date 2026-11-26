<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantSetting;
use Illuminate\Support\Facades\Schema;

/**
 * One WhatsApp number, priced from the number the customer verified.
 * The browser does not choose the amount.
 */
class CloudMbolePrice
{
    public function e164($phone, $dial = null)
    {
        $dial = preg_replace('/\D+/', '', (string) $dial);
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if ($dial !== '') {
            return \App\Support\CountryDialCodes::combine($dial, $digits);
        }
        if ($dial === '' && strlen($digits) >= 11) {
            return '+'.$digits;
        }
        $digits = ltrim($digits, '0');
        if ($dial === '') {
            $dial = '237';
        }

        return '+'.$dial.$digits;
    }

    public function periods()
    {
        return [
            ['days' => 3, 'amount' => '500.00', 'title' => '3-day trial'],
            ['days' => 7, 'amount' => '1500.00', 'title' => '7 days'],
            ['days' => 15, 'amount' => '3000.00', 'title' => '15 days'],
            ['days' => 30, 'amount' => '5000.00', 'title' => '30 days'],
        ];
    }

    public function period($days)
    {
        $days = (int) $days;
        foreach ($this->periods() as $period) {
            if ((int) $period['days'] === $days) {
                return $period;
            }
        }

        return $this->periods()[0];
    }

    public function forMessaging(CloudTenant $tenant, $methodCode)
    {
        $chosen = $this->chosenPeriod($tenant);
        if ($chosen) {
            return ['amount' => $chosen['amount'], 'currency' => 'XAF'];
        }
        $digits = preg_replace('/\D+/', '', (string) $tenant->phone);
        $method = strtoupper((string) $methodCode);
        if ($method === 'VISA' || $method === 'CRYPTO') {
            return ['amount' => '10.00', 'currency' => 'USD'];
        }
        if (strpos($digits, '250') === 0) {
            if (! config('cloud.rwanda_momo')) {
                throw new \RuntimeException('Mobile Money for Rwanda is not available yet. Pay 10 USD by card.');
            }

            return ['amount' => '10000.00', 'currency' => 'RWF'];
        }
        if (strpos($digits, '237') === 0) {
            return ['amount' => '5000.00', 'currency' => 'XAF'];
        }

        throw new \RuntimeException('This number pays 10 USD by card each month.');
    }

    protected function chosenPeriod(CloudTenant $tenant)
    {
        if (! $tenant || ! $tenant->id || ! Schema::hasTable('cloud_tenant_settings')) {
            return null;
        }
        $days = (int) CloudTenantSetting::where('cloud_tenant_id', $tenant->id)->where('key', 'mai_days')->value('value');
        if (! in_array($days, [3, 7, 15, 30], true)) {
            return null;
        }

        return $this->period($days);
    }
}

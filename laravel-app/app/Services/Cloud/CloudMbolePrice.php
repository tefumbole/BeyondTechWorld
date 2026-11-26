<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenant;

/**
 * One WhatsApp number, priced from the number the customer verified.
 * The browser does not choose the amount.
 */
class CloudMbolePrice
{
    public function e164($phone, $dial = null)
    {
        $dial = preg_replace('/\D+/', '', (string) $dial);
        if (! in_array($dial, ['237', '250'], true)) {
            $dial = '';
        }
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '237') === 0 || strpos($digits, '250') === 0) {
            return '+'.$digits;
        }
        $digits = ltrim($digits, '0');
        if ($dial === '') {
            $dial = '237';
        }

        return '+'.$dial.$digits;
    }

    public function forMessaging(CloudTenant $tenant, $methodCode)
    {
        $digits = preg_replace('/\D+/', '', (string) $tenant->phone);
        $method = strtoupper((string) $methodCode);
        if ($method === 'VISA') {
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
}

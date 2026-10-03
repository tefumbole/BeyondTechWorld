<?php

namespace App\Services\Cloud;

use App\WhatsApp\WhatsAppContact;
use Illuminate\Support\Facades\Schema;

/**
 * Finds a display name for a phone the person just verified.
 * Campay holder info is tried first. A single saved WhatsApp name is next.
 * Anything else is left blank so the person types it.
 */
class CloudPhoneNameResolver
{
    public function resolve($phone)
    {
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        $campay = app(CloudCampayBillingProvider::class)->holderName($normalized);
        if ($campay !== '') {
            return $this->pack($normalized, $campay, 'campay');
        }
        $whatsapp = $this->whatsappName($normalized);
        if ($whatsapp !== '') {
            return $this->pack($normalized, $whatsapp, 'whatsapp');
        }

        return $this->pack($normalized, '', '');
    }

    public function split($name)
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            return ['', ''];
        }
        $parts = explode(' ', $name, 2);
        $last = isset($parts[1]) ? trim($parts[1]) : '';

        return [$parts[0], $last !== '' ? $last : $parts[0]];
    }

    protected function whatsappName($normalized)
    {
        if ($normalized === '' || ! Schema::hasTable('whatsapp_contacts')) {
            return '';
        }
        $rows = WhatsAppContact::withoutGlobalScopes()
            ->where('normalized_phone', $normalized)
            ->pluck('wa_name');
        $names = [];
        foreach ($rows as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $names[$name] = true;
            }
        }
        $unique = array_keys($names);

        return count($unique) === 1 ? $unique[0] : '';
    }

    protected function pack($phone, $name, $source)
    {
        list($first, $last) = $this->split($name);

        return [
            'phone' => $phone,
            'name' => $name,
            'first_name' => $first,
            'last_name' => $last,
            'source' => $source,
        ];
    }
}

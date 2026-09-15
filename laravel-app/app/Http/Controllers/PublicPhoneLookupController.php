<?php

namespace App\Http\Controllers;

use App\Services\PeopleDirectoryService;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;

class PublicPhoneLookupController extends Controller
{
    public function lookup(Request $request)
    {
        $phone = trim((string) $request->get('phone', ''));
        $code = trim((string) $request->get('country_code', ''));
        if ($phone === '') {
            return response()->json(['ok' => false, 'found' => false]);
        }

        if ($code !== '') {
            try {
                $phone = WhatsAppPhone::combine($code, $phone);
            } catch (\Throwable $e) {
                // Keep the typed number and let the directory sanitise it.
            }
        }

        $result = app(PeopleDirectoryService::class)->lookupPhoneForForm($phone);
        $customer = null;
        try {
            $customer = app(PeopleDirectoryService::class)->findCustomerByLoosePhone($phone);
        } catch (\Throwable $e) {
        }
        if ($customer && empty($result['email']) && ! empty($customer->email)) {
            $result['email'] = $customer->email;
        }

        return response()->json($result);
    }
}

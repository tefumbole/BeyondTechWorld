<?php

namespace App\Http\Controllers;

use App\Services\Cloud\CloudPhoneNameResolver;
use App\Services\Cloud\CloudSignupOtp;
use Illuminate\Http\Request;

/**
 * Steps before a public company or personal account is created.
 */
class CloudSubscribeController extends Controller
{
    public function identity(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'account_kind' => 'required|in:personal,company',
        ]);
        $resolved = app(CloudPhoneNameResolver::class)->resolve($data['phone']);
        if ($resolved['phone'] === '') {
            return response()->json(['message' => 'Enter a valid phone number.'], 422);
        }
        session(['cloud_account_kind' => $data['account_kind']]);

        return response()->json([
            'phone' => $resolved['phone'],
            'name' => $resolved['name'],
            'first_name' => $resolved['first_name'],
            'last_name' => $resolved['last_name'],
            'source' => $resolved['source'],
        ]);
    }

    public function sendSignupCode(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'account_kind' => 'required|in:personal,company',
        ]);
        try {
            $code = app(CloudSignupOtp::class)->send($data['phone']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        session(['cloud_account_kind' => $data['account_kind']]);
        $body = ['sent' => true];
        if (app()->environment('testing')) {
            $body['testing_code'] = $code;
        }

        return response()->json($body);
    }

    public function verifySignupCode(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'code' => 'required|string|max:12',
        ]);
        try {
            $phone = app(CloudSignupOtp::class)->verify($data['phone'], $data['code']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['verified' => true, 'phone' => $phone]);
    }
}

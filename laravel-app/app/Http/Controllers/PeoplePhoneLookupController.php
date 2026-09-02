<?php

namespace App\Http\Controllers;

use App\Services\PeopleDirectoryService;
use Illuminate\Http\Request;

class PeoplePhoneLookupController extends Controller
{
    public function lookup(Request $request)
    {
        $phone = trim((string) $request->get('phone', ''));
        if ($phone === '') {
            return response()->json(['ok' => false, 'found' => false]);
        }

        return response()->json(app(PeopleDirectoryService::class)->lookupPhoneForForm($phone));
    }
}

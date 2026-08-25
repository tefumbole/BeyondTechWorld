<?php

namespace App\Http\Controllers;

use App\Support\InternshipReportQr;

class InternshipReportVerifyController extends Controller
{
    public function show($token)
    {
        $data = InternshipReportQr::decode($token);

        return view('timesheet.public.verify', [
            'valid' => (bool) $data,
            'data' => $data,
        ]);
    }
}

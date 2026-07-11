<?php

namespace App\Http\Controllers;

use App\Services\PayslipService;
use Illuminate\Support\Facades\Schema;

class PayslipVerifyController extends Controller
{
    protected $payslips;

    public function __construct(PayslipService $payslips)
    {
        $this->payslips = $payslips;
    }

    public function show($code)
    {
        $data = null;
        $error = null;

        if (! Schema::hasTable('hr_payslips')) {
            $error = 'Payslip verification is not available.';
        } else {
            $data = $this->payslips->verify($code);
            if (! $data) {
                $error = 'Payslip not found. Please check the verification code and try again.';
            }
        }

        return view('beyond.payslip.verify', [
            'code' => $code,
            'data' => $data,
            'error' => $error,
        ]);
    }
}

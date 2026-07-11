@extends('beyond.layout')

@section('title', 'Payslip Verification')
@section('meta_description', 'Verify the authenticity of a Beyond Enterprise payslip.')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center p-4 py-16">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="text-center px-6 pt-8 pb-4">
            @if ($data && !empty($data['valid']))
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="check-circle" class="w-9 h-9 text-green-600"></i>
                </div>
                <h1 class="text-xl font-bold text-brand-blue">Verified Payslip</h1>
                <p class="text-sm text-gray-500 mt-1">This is an authentic Beyond Enterprise payslip.</p>
            @else
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="x-circle" class="w-9 h-9 text-red-500"></i>
                </div>
                <h1 class="text-xl font-bold text-brand-blue">Verification Failed</h1>
            @endif
        </div>

        <div class="px-6 pb-8 text-sm">
            @if ($error)
                <p class="text-center text-gray-600 py-4">{{ $error }}</p>
                <p class="text-center text-xs text-gray-400">Code checked: <span class="font-mono">{{ $code }}</span></p>
            @else
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Employee</span><span class="font-semibold text-gray-900">{{ $data['employee_name'] }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Employee No.</span><span class="text-gray-800">{{ $data['staff_code'] }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Position</span><span class="text-gray-800">{{ $data['position'] ?: '—' }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Date of employment</span><span class="text-gray-800">{{ $data['hire_date'] ?: '—' }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Payroll</span><span class="text-gray-800">{{ $data['payroll_title'] }}</span></div>
                <div class="flex justify-between py-2.5"><span class="text-gray-500">Net pay</span><span class="font-bold text-brand-blue">{{ number_format((float) ($data['net_amount'] ?? 0)) }} FCFA</span></div>
                <div class="mt-3 flex items-center justify-center gap-1.5 text-xs text-gray-400">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                    Verification code: <span class="font-mono">{{ $data['verification_code'] }}</span>
                </div>
            @endif
            <p class="text-center text-xs text-gray-400 pt-4 mt-4 border-t">Beyond Enterprise · Human Resources</p>
        </div>
    </div>
</div>
@endsection

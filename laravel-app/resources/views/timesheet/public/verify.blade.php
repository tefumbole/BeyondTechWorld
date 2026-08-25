@extends('beyond.layout')

@section('title', $valid ? 'Internship report valid' : 'Verification failed')
@section('meta_description', 'Verify a Beyond Enterprise internship timesheet report.')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white flex items-center justify-center p-4 py-16">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
        <div class="text-center px-6 pt-8 pb-4">
            @if($valid)
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="shield-check" class="w-9 h-9 text-green-600"></i>
                </div>
                <h1 class="text-xl font-bold text-brand-blue">Status Valid</h1>
                <p class="text-sm text-gray-500 mt-1">This internship report was issued by Beyond Enterprise.</p>
            @else
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="x-circle" class="w-9 h-9 text-red-500"></i>
                </div>
                <h1 class="text-xl font-bold text-brand-blue">Not valid</h1>
                <p class="text-sm text-gray-500 mt-1">This QR code could not be verified.</p>
            @endif
        </div>

        <div class="px-6 pb-8 text-sm">
            @if($valid)
                <div class="flex justify-between border-b py-3">
                    <span class="text-gray-500">Student’s name</span>
                    <span class="font-semibold text-gray-900 text-right pl-4">{{ $data['name'] }}</span>
                </div>
                @if(!empty($data['matricule']))
                    <div class="flex justify-between border-b py-3">
                        <span class="text-gray-500">Matricule</span>
                        <span class="text-gray-800 font-mono">{{ $data['matricule'] }}</span>
                    </div>
                @endif
                <div class="flex justify-between border-b py-3">
                    <span class="text-gray-500">Duration of internship</span>
                    <span class="text-gray-800 text-right pl-4">{{ $data['duration'] ?: '—' }}</span>
                </div>
                <div class="flex justify-between py-3">
                    <span class="text-gray-500">Status</span>
                    <span class="font-bold text-green-700">Valid</span>
                </div>
            @endif
            <p class="text-center text-xs text-gray-400 pt-4 mt-2 border-t">Beyond Enterprise · Internship Office</p>
        </div>
    </div>
</div>
@endsection

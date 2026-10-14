@extends('beyond.layout')

@section('title', 'Letter Verification')
@section('meta_description', 'Verify a Beyond Enterprise letter.')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center p-4 py-16">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="text-center px-6 pt-8 pb-4">
            @if ($data && !empty($data['valid']))
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="check-circle" class="w-9 h-9 text-green-600"></i>
                </div>
                <h1 class="text-xl font-bold text-brand-blue">Letter</h1>
                <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 bg-green-50 border border-green-200 rounded-full px-3 py-1">
                    Status: Valid
                </p>
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
            @else
                <div class="flex justify-between gap-4 border-b py-2.5">
                    <span class="text-gray-500" style="flex-shrink:0">Reference</span>
                    <span class="font-semibold text-gray-900 text-right">{{ $data['reference'] }}</span>
                </div>
                <div class="flex justify-between gap-4 border-b py-2.5">
                    <span class="text-gray-500" style="flex-shrink:0">Subject</span>
                    <span class="font-semibold text-gray-900 text-right">{{ $data['subject'] }}</span>
                </div>
                <div class="flex justify-between gap-4 border-b py-2.5">
                    <span class="text-gray-500" style="flex-shrink:0">Issued to</span>
                    <span class="font-semibold text-gray-900 text-right">{{ $data['issued_to'] }}</span>
                </div>
                <div class="flex justify-between gap-4 py-2.5">
                    <span class="text-gray-500" style="flex-shrink:0">Date</span>
                    <span class="text-gray-800 text-right">{{ $data['date'] }}</span>
                </div>
            @endif
            <p class="text-center text-xs text-gray-400 pt-4 mt-4 border-t">Beyond Enterprise · Letter Verification</p>
        </div>
    </div>
</div>
@endsection

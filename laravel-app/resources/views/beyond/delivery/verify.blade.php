@extends('beyond.layout')

@section('title', 'Delivery Verification')
@section('meta_description', 'Verify a Beyond Enterprise delivery note.')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center p-4 py-16">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="text-center px-6 pt-8 pb-4">
            @if ($data && !empty($data['valid']))
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="check-circle" class="w-9 h-9 text-green-600"></i>
                </div>
                <h1 class="text-xl font-bold text-brand-blue">Delivery Note</h1>
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
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Delivery Ref</span><span class="font-semibold">{{ $data['reference'] }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Sale Ref</span><span>{{ $data['sale_reference'] }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Created</span><span>{{ $data['created_at'] }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Client</span><span class="font-semibold">{{ $data['client'] }}</span></div>
                <div class="flex justify-between border-b py-2.5"><span class="text-gray-500">Amount</span><span class="font-bold text-brand-blue">{{ number_format((float) $data['amount'], 2) }} {{ $data['currency'] }}</span></div>
                <div class="flex justify-between py-2.5"><span class="text-gray-500">Delivery status</span><span class="font-semibold">{{ $data['status'] }}</span></div>
                @if(!empty($data['signed_at']))
                    <div class="flex justify-between border-t py-2.5"><span class="text-gray-500">Signed at</span><span>{{ $data['signed_at'] }}</span></div>
                @endif
            @endif
            <p class="text-center text-xs text-gray-400 pt-4 mt-4 border-t">Beyond Enterprise · Delivery Verification</p>
        </div>
    </div>
</div>
@endsection

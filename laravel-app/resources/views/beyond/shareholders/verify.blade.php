@extends('beyond.layout')

@section('title', 'Verify Signed Agreement')
@section('meta_description', 'Official signed shareholder agreement verification.')

@section('content')
<div class="min-h-screen bg-gray-100 py-8 px-4">
    <div class="max-w-4xl mx-auto space-y-4">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 border border-gray-300 bg-white px-4 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Home
            </a>
            <div class="flex items-center gap-2 text-sm text-gray-600">
                <i data-lucide="shield-check" class="w-4 h-4 text-green-600"></i>
                Official signed agreement verification
            </div>
        </div>

        @if (!empty($error) || empty($shareholder))
            <div class="bg-white rounded-lg shadow p-8 text-center space-y-3">
                <i data-lucide="alert-triangle" class="w-12 h-12 text-amber-500 mx-auto"></i>
                <h1 class="text-xl font-bold text-gray-800">Verification Failed</h1>
                <p class="text-gray-600">{{ $error ?? 'Agreement not found.' }}</p>
            </div>
        @else
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-brand-blue text-white px-6 py-4">
                    <h1 class="text-lg font-bold">Verified Signed Shareholder Agreement</h1>
                    <p class="text-sm text-blue-100 mt-1">
                        {{ $shareholder->full_name }} · Signed {{ $shareholder->agreement_signed_at->format('F j, Y') }}
                    </p>
                </div>

                @include('beyond.shareholders.partials.agreement-document', ['shareholder' => $shareholder, 'investmentLabel' => $investmentLabel])
            </div>
        @endif
    </div>
</div>
@endsection

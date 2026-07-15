@extends('beyond.layout')

@section('title', 'Agreement Signed')

@section('content')
<div class="min-h-screen bg-gray-50 py-16 px-4">
    <div class="max-w-lg mx-auto bg-white rounded-xl shadow-lg border border-gray-100 p-8 text-center">
        <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="check" class="w-8 h-8"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-brand-blue mb-2">Agreement signed</h1>
        <p class="text-gray-600 mb-4">
            Thank you, {{ $application->full_name }}. Your agreement for
            <strong>{{ optional($application->job)->title }}</strong> is on file
            (ref {{ $application->reference_number }}).
        </p>
        <p class="text-sm text-gray-500">A WhatsApp confirmation has been sent to your notification number.</p>
        <a href="{{ url('/') }}" class="inline-block mt-6 text-brand-blue font-semibold hover:underline">Back to Home</a>
    </div>
</div>
@endsection

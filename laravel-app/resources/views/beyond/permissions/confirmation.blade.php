@extends('beyond.layout')

@section('title', 'Permission Submitted')

@section('content')
<div class="min-h-screen bg-gray-50 py-16 px-4">
    <div class="max-w-xl mx-auto bg-white rounded-xl shadow-xl border border-gray-100 p-8 text-center">
        <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
            <i data-lucide="check" class="w-8 h-8 text-green-600"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-brand-blue mb-2">Permission Request Submitted</h1>
        @if(session('success'))
            <p class="text-sm text-green-700 mb-3">{{ session('success') }}</p>
        @endif
        <p class="text-gray-600 mb-4">Your request is pending review. You will receive WhatsApp updates.</p>
        @if($permission)
            <div class="bg-gray-50 rounded-lg px-4 py-3 text-left text-sm space-y-1 mb-6">
                <div><span class="text-gray-500">Reference:</span> <strong>{{ $permission->reference_number }}</strong></div>
                <div><span class="text-gray-500">Name:</span> {{ $permission->full_name }}</div>
                <div><span class="text-gray-500">Role:</span> {{ $permission->company_role }}</div>
                <div><span class="text-gray-500">From:</span> {{ $permission->from_at ? $permission->from_at->format('M j, Y H:i') : '—' }}</div>
                <div><span class="text-gray-500">To:</span> {{ $permission->to_at ? $permission->to_at->format('M j, Y H:i') : '—' }}</div>
                <div><span class="text-gray-500">Reason:</span> {{ $permission->reason }}</div>
            </div>
        @else
            <p class="text-sm text-gray-500 mb-6">Reference: <strong>{{ $reference }}</strong></p>
        @endif
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ url('/permissions') }}" class="inline-flex justify-center bg-brand-gold text-brand-blue font-bold px-5 py-2.5 rounded-md">Apply again</a>
            <a href="{{ url('/') }}" class="inline-flex justify-center border border-gray-200 text-gray-700 font-semibold px-5 py-2.5 rounded-md">Home</a>
        </div>
    </div>
</div>
@endsection

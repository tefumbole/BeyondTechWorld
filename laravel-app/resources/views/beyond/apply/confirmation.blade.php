@extends('beyond.layout')

@section('title', 'Application Submitted')
@section('meta_description', 'Your job application confirmation.')

@section('content')
<div class="min-h-screen bg-gray-50 py-12 px-4">
    <div class="max-w-3xl mx-auto space-y-8">

        @if (! $application)
            <div class="text-center py-16">
                <i data-lucide="alert-circle" class="w-16 h-16 text-red-500 mx-auto mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Application Not Found</h1>
                <p class="text-gray-600 mb-6">Reference: <strong>{{ $reference }}</strong></p>
                <a href="{{ route('apply.index') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-6 py-3 rounded-md font-semibold">Browse Jobs</a>
            </div>
        @else
            <div class="bg-white rounded-xl shadow-lg p-8 text-center border-t-8 border-brand-blue">
                <i data-lucide="check-circle" class="w-20 h-20 text-brand-blue mx-auto mb-4"></i>
                <h1 class="text-3xl font-bold text-brand-blue mb-2">Application Under Review</h1>
                <p class="text-gray-600 text-lg">
                    We have received your application for <span class="font-semibold text-brand-blue">{{ optional($application->job)->title }}</span>
                    and it is now <strong>under review</strong>.
                </p>
                <p class="text-sm text-gray-500 mt-3">
                    Status updates will be sent on WhatsApp to
                    <strong>{{ $application->whatsapp_number ?: $application->phone }}</strong>
                    when messaging is available.
                </p>
                <div class="mt-6 inline-block bg-gray-100 px-6 py-3 rounded-lg border border-gray-200">
                    <span class="text-gray-500 text-sm block mb-1">YOUR REFERENCE NUMBER</span>
                    <span class="text-2xl font-mono font-bold text-brand-gold tracking-wider">{{ $application->reference_number }}</span>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Next Steps</h3>
                <ul class="space-y-4">
                    @foreach ([
                        'Save your reference number — you can use it to track your application.',
                        'Your application is under review. Selected candidates receive an agreement link on WhatsApp to sign.',
                        'You will be notified on WhatsApp at every stage (under review, selected, rejected, or hired).',
                    ] as $i => $step)
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 text-brand-blue flex items-center justify-center font-bold text-sm">{{ $i + 1 }}</span>
                            <p class="text-gray-600">{{ $step }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex flex-wrap justify-center gap-4">
                @auth('beyond')
                    <a href="{{ route('applicant.dashboard') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold hover:bg-brand-dark">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Go to Candidate Portal
                    </a>
                @else
                    <a href="{{ url('/login') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold hover:bg-brand-dark">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Log in to Track Application
                    </a>
                @endauth
                <a href="{{ route('apply.index') }}" class="inline-flex items-center gap-2 border border-gray-300 px-5 py-2.5 rounded-md font-medium text-gray-700 hover:bg-gray-50">
                    Browse More Jobs
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

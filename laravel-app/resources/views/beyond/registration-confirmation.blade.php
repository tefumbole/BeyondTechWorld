@extends('beyond.layout')

@section('title', 'Registration Confirmation')
@section('meta_description', 'Your Beyond Enterprise training registration confirmation.')

@section('content')
<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto space-y-8">

        @if (! $registration)
            <div class="text-center py-16">
                <i data-lucide="alert-circle" class="w-16 h-16 text-red-500 mx-auto mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Registration Not Found</h1>
                <p class="text-gray-600 mb-6">We couldn't find a registration with reference: <strong>{{ $reference }}</strong></p>
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-6 py-3 rounded-md font-semibold">Return Home</a>
            </div>
        @else
            <div class="text-center space-y-4">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100">
                    <i data-lucide="check-circle-2" class="h-10 w-10 text-green-600"></i>
                </div>
                <h1 class="text-3xl font-extrabold text-gray-900">Registration Complete!</h1>
                <p class="text-lg text-gray-600">Thank you, {{ $registration->client_name }}. Your registration has been received.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border-t-4 border-t-brand-gold overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-100 px-8 py-6 text-center">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Reference Number</p>
                    <p class="text-3xl font-mono font-bold text-brand-blue mt-2">{{ $registration->reference_number }}</p>
                </div>
                <div class="p-8 space-y-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $registration->client_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $registration->client_email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $registration->client_phone }}</dd>
                        </div>
                        @if ($registration->company_name)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Company</dt>
                                <dd class="mt-1 font-semibold text-gray-900">{{ $registration->company_name }}</dd>
                            </div>
                        @endif
                    </dl>
                    <div class="pt-4 border-t border-gray-100">
                        <dt class="text-sm font-medium text-gray-500 mb-2">Selected Courses</dt>
                        <div class="flex flex-wrap gap-2">
                            @foreach (explode(', ', $registration->course_names) as $courseName)
                                <span class="bg-blue-50 text-brand-blue text-sm px-3 py-1 rounded-full font-medium">{{ $courseName }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4">What Happens Next?</h3>
                <ul class="space-y-4">
                    @foreach ([
                        'Our team will review your registration and contact you within 24 hours.',
                        'You will receive payment instructions and your enrollment schedule.',
                        'Log in to your student portal to track your course progress.',
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
                    <a href="{{ route('student.dashboard') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold hover:bg-brand-dark">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Go to Student Portal
                    </a>
                @else
                    <a href="{{ url('/login') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold hover:bg-brand-dark">
                        <i data-lucide="log-in" class="w-4 h-4"></i> Log in to Track Progress
                    </a>
                @endauth
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 border border-gray-300 px-5 py-2.5 rounded-md font-medium text-gray-700 hover:bg-gray-50">
                    Return to Home
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

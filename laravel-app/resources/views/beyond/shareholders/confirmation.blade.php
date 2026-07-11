@extends('beyond.layout')

@section('title', 'Registration Confirmation')
@section('meta_description', 'Your shareholder registration confirmation.')

@section('content')
<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto space-y-8">

        @if (! $shareholder)
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
                <h1 class="text-3xl font-extrabold text-gray-900">Registration Successful!</h1>
                <p class="text-lg text-gray-600">Thank you for registering as a shareholder. Your application has been received.</p>
            </div>

            <div class="bg-white rounded-xl shadow-lg border-t-4 border-t-brand-light overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-100 px-8 py-6 text-center">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Reference Number</p>
                    <p class="text-3xl font-mono font-bold text-brand-light mt-2">{{ $shareholder->reference_number }}</p>
                </div>
                <div class="p-8">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-8">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $shareholder->full_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $shareholder->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone Number</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $shareholder->full_phone_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Shares Requested</dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $shareholder->shares_assigned }}</dd>
                        </div>
                        <div class="md:col-span-2 pt-4 border-t border-gray-100 flex justify-between items-center">
                            <dt class="text-base font-medium text-gray-500">Total Investment Amount</dt>
                            <dd class="text-2xl font-bold text-brand-light">{{ $investmentLabel }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4">What Happens Next?</h3>
                <ul class="space-y-4">
                    @foreach ([
                        'We will review your application and verify your details within 24–48 hours.',
                        'You will receive an email with payment instructions and the shareholder agreement.',
                        'Once payment is confirmed, you will be issued a digital share certificate.',
                    ] as $i => $step)
                        <li class="flex gap-3">
                            <span class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 text-brand-light flex items-center justify-center font-bold text-sm">{{ $i + 1 }}</span>
                            <p class="text-gray-600">{{ $step }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex flex-wrap justify-center gap-4">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 border border-gray-300 px-5 py-2.5 rounded-md font-medium text-gray-700 hover:bg-gray-50">
                    <i data-lucide="download" class="w-4 h-4"></i> Save Confirmation
                </button>
                <a href="{{ route('shareholders.verify', $shareholder->id) }}" class="inline-flex items-center gap-2 border border-brand-blue text-brand-blue px-5 py-2.5 rounded-md font-medium hover:bg-blue-50">
                    <i data-lucide="shield-check" class="w-4 h-4"></i> View Signed Agreement
                </a>
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 bg-brand-blue text-white px-5 py-2.5 rounded-md font-semibold hover:bg-brand-dark">
                    Return to Home <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

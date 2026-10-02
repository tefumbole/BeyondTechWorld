@extends('beyond.layout')

@section('title', 'Subscriptions')
@section('meta_description', 'Subscribe to Beyond Cloud modules. Prices and trials come from the current plan list.')

@section('content')
<section class="bg-gradient-to-br from-brand-blue via-[#0052A3] to-brand-blue py-8 sm:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-2">
            Beyond <span class="text-brand-gold">Subscriptions</span>
        </h1>
        <p class="text-white/90 max-w-2xl mx-auto text-sm sm:text-base">Choose a module, start a trial, then pay with the methods already on this site.</p>
    </div>
</section>

<section class="py-8 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($plans->isEmpty())
            <p class="text-center text-gray-600">Plans are not available yet.</p>
        @else
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @foreach($plans as $plan)
                    <article class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col">
                        <h2 class="text-lg font-bold text-brand-blue">{{ $plan->name }}</h2>
                        <p class="text-sm text-gray-600 mt-2 flex-1">{{ $plan->module ? $plan->module->description : '' }}</p>
                        <p class="mt-4 text-2xl font-extrabold text-gray-900">{{ number_format((float) $plan->price, 0) }} <span class="text-base font-semibold text-gray-500">{{ $plan->currency }}</span></p>
                        <p class="text-sm text-gray-500">per {{ strtolower($plan->billing_interval ?: 'month') }}</p>
                        <p class="text-sm text-brand-blue mt-2">Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</p>
                        <a href="{{ url('/cloud/register') }}" class="mt-4 inline-flex justify-center bg-brand-gold hover:bg-[#C19B2A] text-brand-blue font-bold rounded-full px-4 py-2.5">Start</a>
                    </article>
                @endforeach
            </div>
            @if($methods->isNotEmpty())
                <p class="text-center text-sm text-gray-600 mt-6">Pay with {{ $methods->pluck('name')->implode(' or ') }}.</p>
            @endif
            <p class="text-center mt-4">
                <a href="{{ url('/cloud/login') }}" class="text-brand-blue font-semibold hover:underline">Already have a company portal? Sign in</a>
            </p>
        @endif
    </div>
</section>
@endsection

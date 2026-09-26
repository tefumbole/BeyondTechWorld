@extends('beyond.layout')

@section('title', 'Invest in Shares')
@section('meta_description', 'Secure your shares in Beyond Enterprise. Join our community of shareholders and be part of our growth story.')

@section('content')
<div class="min-h-screen bg-slate-50">

    <section class="bg-brand-blue text-white py-5 sm:py-6">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight m-0 mb-1">Invest in Shares</h1>
            <p class="text-sm text-blue-100 m-0 max-w-xl mx-auto">Join our community of shareholders and secure your shares.</p>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 py-8 -mt-3 relative z-20">
        <div class="grid lg:grid-cols-3 gap-6 items-start">

            <div class="lg:col-span-1 space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                    <h2 class="text-brand-blue font-bold text-base m-0">Why invest?</h2>
                    <ul class="space-y-2.5 m-0 p-0 list-none">
                        @foreach (['Proven track record of growth', 'Transparent financial reporting', 'Quarterly dividend payouts', 'Voting rights at AGM'] as $item)
                            <li class="flex items-start gap-2 text-slate-600 text-sm">
                                <i data-lucide="check" class="h-4 w-4 text-brand-blue flex-shrink-0 mt-0.5"></i>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 m-0 mb-1">Share value</p>
                    <div class="text-2xl font-extrabold text-brand-blue">{{ $priceLabel }}</div>
                    <p class="text-slate-500 text-sm m-0 mt-1">Per share · minimum 1 share</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm text-sm text-slate-600">
                    <strong class="text-brand-blue">{{ number_format($settings['available_shares']) }}</strong> shares available for subscription.
                </div>
            </div>

            <div class="lg:col-span-2">
                @include('beyond.shareholders.partials.registration-form')
            </div>
        </div>
    </div>
</div>
@endsection

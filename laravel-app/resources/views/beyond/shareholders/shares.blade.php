@extends('beyond.layout')

@section('title', 'Invest in Shares')
@section('meta_description', 'Secure your shares in Beyond Enterprise. Join our community of shareholders and be part of our growth story.')

@section('content')
<div class="min-h-screen bg-gray-50">

    <section class="bg-brand-blue text-white py-20 relative overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-10" style="background-image:url('https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?auto=format&fit=crop&w=1470&q=80');"></div>
        <div class="max-w-7xl mx-auto px-4 relative z-10 text-center">
            <div class="inline-flex items-center gap-2 bg-blue-500/30 backdrop-blur-md px-4 py-1.5 rounded-full text-blue-100 text-sm font-medium mb-6 border border-blue-400/30">
                <i data-lucide="unlock" class="h-3.5 w-3.5"></i>
                Public Offering — No Login Required
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">Invest in the Future</h1>
            <p class="text-xl md:text-2xl text-blue-100 max-w-2xl mx-auto">
                Join our community of shareholders and be part of our growth story. Secure your shares instantly.
            </p>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 py-12 -mt-10 relative z-20">
        <div class="grid lg:grid-cols-3 gap-8 items-start">

            <div class="lg:col-span-1 space-y-6">
                <div class="rounded-xl shadow-xl bg-white/95 backdrop-blur p-6 space-y-4">
                    <div class="flex items-center gap-3 text-brand-blue font-bold text-lg">
                        <i data-lucide="shield" class="h-6 w-6"></i> Why Invest?
                    </div>
                    <ul class="space-y-3">
                        @foreach (['Proven Track Record of Growth', 'Transparent Financial Reporting', 'Quarterly Dividend Payouts', 'Voting Rights at AGM'] as $item)
                            <li class="flex items-start gap-2 text-gray-600">
                                <i data-lucide="check" class="h-5 w-5 text-green-500 flex-shrink-0 mt-0.5"></i>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-xl shadow-lg bg-brand-light text-white p-6">
                    <h4 class="font-bold text-lg mb-2 flex items-center gap-2">
                        <i data-lucide="trending-up" class="h-5 w-5"></i> Share Value
                    </h4>
                    <div class="text-3xl font-extrabold mb-1">{{ $priceLabel }}</div>
                    <p class="text-blue-100 text-sm">Current price per share unit. Minimum purchase is 1 share.</p>
                </div>

                <div class="rounded-xl shadow-lg bg-gray-900 text-white p-6">
                    <h4 class="font-bold text-lg mb-2 flex items-center gap-2">
                        <i data-lucide="users" class="h-5 w-5"></i> Community
                    </h4>
                    <p class="text-gray-300 text-sm">
                        Join investors who have partnered with us to build sustainable technology solutions across Africa.
                    </p>
                </div>

                <div class="rounded-xl border border-brand-gold/30 bg-amber-50 p-4 text-sm text-gray-700">
                    <strong class="text-brand-blue">{{ number_format($settings['available_shares']) }}</strong> shares currently available for subscription.
                </div>
            </div>

            <div class="lg:col-span-2">
                @include('beyond.shareholders.partials.registration-form')
            </div>
        </div>
    </div>
</div>
@endsection

@extends('beyond.layout')

@section('title', 'IT Consultancy & AV Solutions')
@section('meta_description', 'Beyond Enterprise — your technology bridge to Kigali. IT consultancy, networking, CCTV security, and professional audio-visual solutions in Rwanda.')
@section('body_class', 'home-lock')

@section('content')
{{-- Single-viewport homepage: copy + CTAs pin to the top so the Kigali skyline stays visible --}}
<section class="home-hero relative flex flex-col items-center justify-start overflow-hidden w-full">
    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image:url('{{ \App\Support\SiteContent::image('home.hero_image', '/branding/beyond-hero.png') }}');">
        <div class="absolute inset-0 bg-black/15"></div>
    </div>

    @for ($i = 0; $i < 6; $i++)
        <div class="absolute w-2 h-2 rounded-full bg-brand-gold/40 floaty"
             style="left: {{ 10 + $i * 15 }}%; top: {{ 20 + ($i % 3) * 25 }}%; animation-delay: {{ $i * 0.4 }}s;"></div>
    @endfor

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center w-full pt-3 sm:pt-5 md:pt-6 pb-4">
        <div class="flex flex-col items-center">
            <img src="{{ \App\Support\SiteBrand::logoUrl($general_setting ?? null) }}" alt="{{ \App\Support\SiteBrand::siteTitle($general_setting ?? null) }}" class="h-10 sm:h-12 md:h-14 w-auto object-contain drop-shadow-2xl mb-2 sm:mb-3">
            <h1 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-2 drop-shadow-2xl tracking-tight">
                {!! \App\Support\SiteContent::html('home.hero_title', 'Your Technology Bridge to <span class="text-brand-gold">Africa</span>') !!}
            </h1>
            <p class="text-sm sm:text-base md:text-lg text-white/90 font-light max-w-3xl mx-auto drop-shadow-md mb-3 sm:mb-4">
                {{ \App\Support\SiteContent::text('home.hero_subtitle', 'Professional IT Consultancy, Enterprise Networking, and Audio-Visual Production, Cloud, AI and Cyber') }}
            </p>
            <div class="w-full flex flex-col sm:flex-row items-center justify-center gap-2.5 sm:gap-4 flex-wrap">
                <a href="{{ url('/trainings') }}"
                   class="bg-brand-gold hover:bg-[#b5952f] text-brand-blue h-11 md:h-12 px-5 md:px-7 text-sm md:text-base font-bold shadow-[0_0_15px_rgba(212,175,55,0.4)] rounded-full hover:scale-105 transition-transform inline-flex items-center justify-center">
                    {{ \App\Support\SiteContent::text('home.cta_primary', 'Get a Free Quotation') }} <i data-lucide="arrow-right" class="ml-2 w-4 h-4 md:w-5 md:h-5"></i>
                </a>
                <a href="{{ url('/rentals') }}"
                   class="h-11 md:h-12 px-5 md:px-7 text-sm md:text-base font-bold rounded-full shadow-xl hover:shadow-2xl bg-white/15 hover:bg-white/25 border border-brand-gold/80 backdrop-blur-sm text-brand-gold inline-flex items-center justify-center gap-2 transition-all">
                    <i data-lucide="package" class="w-4 h-4 md:w-5 md:h-5"></i> Rentals
                </a>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('mbole-ai-open'))"
                   class="h-11 md:h-12 px-5 md:px-7 text-sm md:text-base font-bold rounded-full shadow-xl hover:shadow-2xl bg-brand-light/40 hover:bg-brand-light/60 border border-white/30 backdrop-blur-sm text-white inline-flex items-center justify-center gap-2 transition-all">
                    <img src="{{ url('public/branding/mbole-ai.png') }}" alt="" class="w-5 h-5 md:w-6 md:h-6 rounded-full object-cover"> Chat with Mbole AI
                </button>
            </div>
        </div>
    </div>
</section>
@endsection

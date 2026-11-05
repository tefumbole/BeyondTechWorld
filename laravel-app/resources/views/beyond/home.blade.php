@extends('beyond.layout')

@section('title', 'IT Consultancy & AV Solutions')
@section('meta_description', 'Beyond Enterprise — your technology bridge to Kigali. IT consultancy, networking, CCTV security, and professional audio-visual solutions in Rwanda.')
@section('body_class', 'home-lock')

@push('head')
<link rel="preload" as="image" href="{{ \App\Support\SiteContent::image('home.hero_image', '/branding/beyond-hero.png') }}">
@endpush

@section('content')
{{-- Single-viewport homepage: the title sits on the skyline. --}}
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
            <p class="home-lead text-sm sm:text-base md:text-lg text-white/90 font-light mx-auto drop-shadow-md mb-0">
                {{ \App\Support\SiteContent::text('home.hero_subtitle', 'Professional IT Consultancy, Enterprise Networking, and Audio-Visual Production, Cloud, AI and Cyber') }}
            </p>
        </div>
    </div>
</section>
@endsection

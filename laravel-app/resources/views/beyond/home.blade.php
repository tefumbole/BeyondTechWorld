@extends('beyond.layout')

@section('title', 'IT Consultancy & AV Solutions')
@section('meta_description', 'Beyond Enterprise — your technology bridge to Kigali. IT consultancy, networking, CCTV security, and professional audio-visual solutions in Rwanda.')

@section('content')

@php
    $services = [
        ['icon' => 'network', 'title' => 'IT Consultancy', 'desc' => 'Enterprise-grade IT solutions and infrastructure planning'],
        ['icon' => 'share-2', 'title' => 'Networks', 'desc' => 'Professional networking design, deployment, and management'],
        ['icon' => 'shield', 'title' => 'CCTV & Security', 'desc' => 'Advanced surveillance and security systems'],
        ['icon' => 'mic', 'title' => 'Sound & Audio', 'desc' => 'Professional audio engineering for events and venues'],
        ['icon' => 'monitor', 'title' => 'Screens & Lighting', 'desc' => 'LED screens and professional lighting solutions'],
        ['icon' => 'cable', 'title' => 'Fiber Optics', 'desc' => 'High-speed fiber connectivity and splicing services'],
    ];
    $whyUs = [
        ['icon' => 'check-circle-2', 'title' => 'Engineering Standards', 'desc' => 'Built with precision and best practices'],
        ['icon' => 'shield', 'title' => 'Reliability', 'desc' => 'Dependable systems you can trust'],
        ['icon' => 'zap', 'title' => 'Fast Support', 'desc' => 'Quick response times and expert assistance'],
        ['icon' => 'trending-up', 'title' => 'Scalable Solutions', 'desc' => 'Systems that grow with your needs'],
    ];
    $industries = [
        ['icon' => 'building-2', 'name' => 'Companies'],
        ['icon' => 'church', 'name' => 'Churches'],
        ['icon' => 'calendar', 'name' => 'Events'],
        ['icon' => 'school', 'name' => 'Schools'],
        ['icon' => 'heart', 'name' => 'NGOs'],
        ['icon' => 'home', 'name' => 'Homes'],
    ];
    $testimonials = [
        ['name' => 'Client A', 'role' => 'CEO, Tech Company', 'content' => 'Beyond Enterprise delivered exceptional networking solutions for our office. Professional and reliable.'],
        ['name' => 'Client B', 'role' => 'Event Organizer', 'content' => 'Their sound and lighting setup made our event unforgettable. Highly recommended!'],
        ['name' => 'Client C', 'role' => 'School Administrator', 'content' => 'The CCTV system they installed has greatly improved our campus security.'],
    ];
@endphp

{{-- Hero --}}
<section class="relative min-h-screen flex flex-col items-center justify-center overflow-hidden py-20 md:py-0">
    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image:url('{{ \App\Support\SiteContent::image('home.hero_image', '/branding/beyond-hero.png') }}');">
        <div class="absolute inset-0 bg-black/15"></div>
    </div>

    @for ($i = 0; $i < 6; $i++)
        <div class="absolute w-2 h-2 rounded-full bg-brand-gold/40 floaty"
             style="left: {{ 10 + $i * 15 }}%; top: {{ 20 + ($i % 3) * 25 }}%; animation-delay: {{ $i * 0.4 }}s;"></div>
    @endfor

    <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center w-full mt-20 md:mt-0">
        <div class="mb-8 flex flex-col items-center">
            <img src="{{ \App\Support\SiteBrand::logoUrl($general_setting ?? null) }}" alt="{{ \App\Support\SiteBrand::siteTitle($general_setting ?? null) }}" class="h-20 md:h-24 w-auto object-contain mb-6 drop-shadow-2xl">
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-bold text-white mb-4 drop-shadow-2xl tracking-tight">
                {!! \App\Support\SiteContent::html('home.hero_title', 'Your Technology Bridge to <span class="text-brand-gold">Kigali</span>') !!}
            </h1>
            <p class="text-xl md:text-2xl text-white/90 font-light max-w-3xl mx-auto drop-shadow-md">
                {{ \App\Support\SiteContent::text('home.hero_subtitle', 'Professional IT Consultancy, Enterprise Networking, and Audio-Visual Production, Cloud, AI and Cyber') }}
            </p>
        </div>
        <div class="w-full flex flex-col sm:flex-row items-center justify-center gap-6">
            <a href="{{ url('/about') }}#contact"
               class="bg-brand-gold hover:bg-[#b5952f] text-brand-blue h-14 px-8 text-lg font-bold shadow-[0_0_15px_rgba(212,175,55,0.4)] rounded-full hover:scale-105 transition-transform inline-flex items-center justify-center">
                {{ \App\Support\SiteContent::text('home.cta_primary', 'Get a Free Quote') }} <i data-lucide="arrow-right" class="ml-2 w-5 h-5"></i>
            </a>
            <a href="https://wa.me/237675321739" target="_blank" rel="noopener"
               class="h-14 px-8 text-lg font-bold rounded-full shadow-xl hover:shadow-2xl bg-brand-light/40 hover:bg-brand-light/60 border border-white/30 backdrop-blur-sm text-white inline-flex items-center justify-center gap-2 transition-all">
                <i data-lucide="message-circle" class="w-5 h-5"></i> Chat on WhatsApp
            </a>
        </div>
    </div>
</section>

{{-- Services --}}
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-brand-blue mb-4">{{ \App\Support\SiteContent::text('home.services_heading', 'Our Services') }}</h2>
            <p class="text-xl text-gray-600">{{ \App\Support\SiteContent::text('home.services_subheading', 'Comprehensive technology solutions for your needs') }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach ($services as $s)
                <div class="bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 p-8 border border-gray-100">
                    <div class="text-brand-light mb-4"><i data-lucide="{{ $s['icon'] }}" class="w-12 h-12"></i></div>
                    <h3 class="text-2xl font-semibold text-brand-blue mb-3">{{ $s['title'] }}</h3>
                    <p class="text-gray-700">{{ $s['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Why Beyond --}}
<section class="py-16 bg-brand-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-white mb-4">{{ \App\Support\SiteContent::text('home.why_heading', 'Why Beyond Enterprise?') }}</h2>
            <p class="text-xl text-gray-300">{{ \App\Support\SiteContent::text('home.why_subheading', 'Excellence in every solution we deliver') }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach ($whyUs as $f)
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-6 text-center hover:bg-white/20 transition-all duration-300 border border-white/5">
                    <div class="text-brand-gold mb-4 flex justify-center"><i data-lucide="{{ $f['icon'] }}" class="w-10 h-10"></i></div>
                    <h3 class="text-xl font-semibold text-white mb-2">{{ $f['title'] }}</h3>
                    <p class="text-gray-300 text-sm">{{ $f['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Industries --}}
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-brand-blue mb-4">{{ \App\Support\SiteContent::text('home.industries_heading', 'Industries We Serve') }}</h2>
            <p class="text-xl text-gray-600">{{ \App\Support\SiteContent::text('home.industries_subheading', 'Trusted by diverse organizations across Africa and the World') }}</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            @foreach ($industries as $ind)
                <div class="bg-white rounded-xl shadow-md p-6 text-center border border-gray-100 hover:shadow-lg transition-shadow">
                    <div class="text-brand-light mb-3 flex justify-center"><i data-lucide="{{ $ind['icon'] }}" class="w-10 h-10"></i></div>
                    <h3 class="text-lg font-semibold text-brand-blue">{{ $ind['name'] }}</h3>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Events --}}
@if(!empty($homeEvents) && $homeEvents->isNotEmpty())
<section class="py-16 bg-white border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-brand-blue mb-4">Upcoming Events</h2>
            <p class="text-xl text-gray-600">Highlights and events from Beyond Enterprise</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($homeEvents as $ev)
                <a href="{{ url('/events/' . $ev['slug']) }}" class="group block bg-white rounded-xl border border-gray-200 hover:border-brand-blue hover:shadow-xl transition overflow-hidden">
                    <div class="relative h-44 bg-gray-200 overflow-hidden">
                        @if(!empty($ev['flyer']))
                            <img src="{{ $ev['flyer'] }}" alt="{{ $ev['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-blue to-brand-light">
                                <i data-lucide="calendar" class="w-12 h-12 text-white opacity-60"></i>
                            </div>
                        @endif
                        @if(!empty($ev['start']))
                            <span class="absolute top-3 right-3 bg-brand-gold text-brand-blue text-xs font-bold px-3 py-1 rounded-full">{{ $ev['start']->format('M d') }}</span>
                        @endif
                    </div>
                    <div class="p-5">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-brand-blue line-clamp-2 mb-2">{{ $ev['title'] }}</h3>
                        @if(!empty($ev['start']))
                            <p class="text-sm text-gray-600 mb-1"><i data-lucide="calendar" class="w-4 h-4 inline text-brand-blue"></i> {{ $ev['start']->format('D, M j, Y g:i A') }}</p>
                        @endif
                        @if(!empty($ev['venue']))
                            <p class="text-sm text-gray-600 line-clamp-1"><i data-lucide="map-pin" class="w-4 h-4 inline text-brand-blue"></i> {{ $ev['venue'] }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="text-center mt-10">
            <a href="{{ url('/events') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-brand-blue text-white font-semibold hover:bg-brand-dark transition">
                View all events <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
</section>
@endif

{{-- Testimonials --}}
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-brand-blue mb-4">{{ \App\Support\SiteContent::text('home.testimonials_heading', 'What Our Clients Say') }}</h2>
            <p class="text-xl text-gray-600">{{ \App\Support\SiteContent::text('home.testimonials_subheading', 'Trusted by businesses and organizations across Kigali') }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach ($testimonials as $t)
                <div class="bg-white rounded-xl shadow-md p-8 border border-gray-200">
                    <p class="text-gray-700 italic mb-6">"{{ $t['content'] }}"</p>
                    <div>
                        <p class="font-semibold text-brand-blue">{{ $t['name'] }}</p>
                        <p class="text-sm text-gray-500">{{ $t['role'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="py-16 bg-gradient-to-r from-brand-blue via-brand-light to-brand-blue">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-white mb-6">{{ \App\Support\SiteContent::text('home.cta_heading', 'Ready to Get Started?') }}</h2>
        <p class="text-xl text-gray-200 mb-8">{{ \App\Support\SiteContent::text('home.cta_text', 'Contact us today for a consultation and let us bridge your technology needs.') }}</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="https://wa.me/237675321739" target="_blank" rel="noopener"
               class="px-8 py-4 text-lg rounded-lg shadow-xl hover:shadow-2xl bg-[#25D366] hover:bg-[#1EBE57] text-white font-semibold inline-flex items-center justify-center gap-2">
                <i data-lucide="message-circle" class="w-5 h-5"></i> Chat on WhatsApp
            </a>
            <a href="mailto:info@beyondtechworld.com"
               class="bg-white text-brand-blue hover:bg-gray-100 px-8 py-4 text-lg rounded-lg shadow-xl hover:shadow-2xl font-semibold inline-flex items-center justify-center gap-2">
                <i data-lucide="mail" class="w-5 h-5"></i> Email Us
            </a>
        </div>
    </div>
</section>

@endsection

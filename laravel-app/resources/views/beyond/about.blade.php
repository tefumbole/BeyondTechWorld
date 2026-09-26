@extends('beyond.layout')

@section('title', 'About Beyond Enterprise | Our Vision & Leadership')
@section('meta_description', 'Mission, vision, and leadership of Beyond Enterprise — bridging technology and innovation across Africa and beyond.')

@section('content')

<section class="py-10 sm:py-14 bg-slate-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-5 sm:gap-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <h2 class="text-lg sm:text-xl font-extrabold tracking-wide text-brand-blue uppercase m-0">Our Vision</h2>
                <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
                <p class="text-slate-600 text-sm sm:text-base leading-relaxed m-0">
                    {{ \App\Support\SiteContent::text('about.vision_text', 'To be the trusted technology bridge connecting Africa to world-class IT infrastructure, innovation, and sustainable digital growth.') }}
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <h2 class="text-lg sm:text-xl font-extrabold tracking-wide text-brand-blue uppercase m-0">Our Mission</h2>
                <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
                <p class="text-slate-600 text-sm sm:text-base leading-relaxed m-0">
                    {{ \App\Support\SiteContent::text('about.mission_text', 'To empower organizations in Africa and beyond with robust, scalable, and secure technology infrastructure. We strive to be the bridge that connects complex technological challenges with simple, effective, and sustainable solutions.') }}
                </p>
            </div>
        </div>
    </div>
</section>

@if(isset($leaders) && $leaders->count())
<section id="leadership" class="py-16 sm:py-20 bg-brand-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 sm:mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">{{ \App\Support\SiteContent::text('about.leadership_heading', 'Our Leadership') }}</h2>
            <div class="h-1 w-24 bg-brand-gold mx-auto"></div>
            <p class="mt-4 text-lg sm:text-xl text-gray-300">{{ \App\Support\SiteContent::text('about.leadership_subtext', 'The visionaries driving Beyond Enterprise forward') }}</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
            @foreach($leaders as $leader)
                <div class="group flex flex-col items-center text-center">
                    <div class="relative mb-6">
                        <div class="absolute inset-0 bg-gradient-to-br from-brand-gold to-[#F7E7CE] rounded-full blur opacity-75 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="relative w-48 h-48 rounded-full p-1 bg-gradient-to-br from-brand-gold to-[#8a701f]">
                            <div class="w-full h-full rounded-full overflow-hidden border-4 border-brand-blue bg-gray-200">
                                @if($leader->photoPublicUrl())
                                    <img src="{{ $leader->photoPublicUrl() }}" alt="{{ $leader->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                        <i data-lucide="user" class="w-16 h-16"></i>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-white">
                        {{ $leader->name }}
                        @if($leader->country)
                            <span class="ml-1" title="{{ $leader->country }}">{{ $leader->countryFlag() ?: '' }}</span>
                        @endif
                    </h3>
                    <p class="mt-1 text-sm font-semibold uppercase tracking-wide text-brand-gold">{{ $leader->title }}</p>
                    @if($leader->description)
                        <p class="mt-3 text-gray-300 text-sm leading-relaxed max-w-sm">{{ $leader->description }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection

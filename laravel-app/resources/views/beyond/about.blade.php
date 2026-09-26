@extends('beyond.layout')

@section('title', 'About Beyond Enterprise | Our Vision & Mission')
@section('meta_description', 'Mission and vision of Beyond Enterprise — bridging technology and innovation across Africa and beyond.')

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

@endsection

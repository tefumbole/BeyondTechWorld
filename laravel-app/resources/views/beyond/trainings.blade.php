@extends('beyond.layout')

@section('title', 'Professional IT Training Programs 2026')
@section('meta_description', 'Advanced technical training in AI, Cloud Computing, Cybersecurity, IT Consultancy, VoIP, Network Infrastructure, and CCTV Systems.')

@php
    $iconMap = [
        'Brain' => 'brain', 'Cloud' => 'cloud', 'Shield' => 'shield', 'Briefcase' => 'briefcase',
        'Phone' => 'phone', 'Network' => 'network', 'Video' => 'video',
    ];
@endphp

@section('content')

<section class="bg-gradient-to-br from-brand-blue via-[#0052A3] to-brand-blue py-4 sm:py-5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-3">
            Professional <span class="text-brand-gold">IT Training</span>
        </h1>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="#programs" class="bg-brand-gold hover:bg-[#C19B2A] text-brand-blue px-6 py-2.5 text-sm font-bold shadow-lg hover:scale-105 transition-transform rounded-full">Explore Programs</a>
            <a href="{{ url('/register-now') }}" class="border-2 border-white text-white hover:bg-white hover:text-brand-blue px-6 py-2.5 text-sm font-bold shadow-lg hover:scale-105 transition-all rounded-full">Register Now</a>
        </div>
    </div>
</section>

<section class="py-6 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 text-center">
            <div><div class="text-2xl sm:text-3xl font-bold text-brand-blue mb-1">{{ count($programs) }}</div><div class="text-gray-600 text-sm">Training Programs</div></div>
            <div><div class="text-2xl sm:text-3xl font-bold text-brand-blue mb-1">8-14</div><div class="text-gray-600 text-sm">Weeks Duration</div></div>
            <div><div class="text-2xl sm:text-3xl font-bold text-brand-blue mb-1">100%</div><div class="text-gray-600 text-sm">Hands-on Labs</div></div>
            <div><div class="text-2xl sm:text-3xl font-bold text-brand-blue mb-1">24/7</div><div class="text-gray-600 text-sm">Support Access</div></div>
        </div>
    </div>
</section>

<section id="programs" class="py-6 sm:py-8 bg-gradient-to-b from-gray-50 to-white" x-data="{ expanded: null }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-5">
            <h2 class="text-2xl sm:text-3xl font-bold text-brand-blue">Our Courses</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
            @forelse ($programs as $module)
                @php
                    $icon = $iconMap[$module['icon'] ?? ''] ?? 'briefcase';
                    $expandKey = $loop->index;
                    $color = $module['color'] ?? '#003D82';
                @endphp
                <div class="program-card bg-white rounded-2xl shadow-md overflow-hidden border-2 border-gray-200 transition-all duration-200"
                     :class="expanded === {{ $expandKey }} ? 'md:col-span-2 border-brand-gold shadow-xl' : ''"
                     style="--program-color: {{ $color }}">
                    <button type="button" @click="expanded = expanded === {{ $expandKey }} ? null : {{ $expandKey }}"
                            class="program-card-btn w-full px-5 md:px-6 py-5 flex items-center justify-between text-left transition-colors duration-200">
                        <div class="flex items-center space-x-3 md:space-x-4 flex-1 min-w-0">
                            <div class="p-3 rounded-xl flex-shrink-0 transition-transform duration-200 program-card-icon" style="background-color: {{ $color }}18">
                                <i data-lucide="{{ $icon }}" class="w-7 h-7 md:w-8 md:h-8" style="color: {{ $color }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-lg md:text-xl font-bold text-brand-blue mb-0.5 truncate">{{ $module['title'] }}</h3>
                                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                                    @if(!empty($module['category']))
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background-color: {{ $color }}18; color: {{ $color }}">{{ $module['category'] }}</span>
                                    @endif
                                    @if(!empty($module['duration']))
                                        <span class="font-semibold">{{ $module['duration'] }}</span>
                                    @endif
                                    @if(!empty($module['deliveryMode']))
                                        <span class="hidden sm:inline text-gray-400">•</span>
                                        <span class="hidden sm:inline truncate">{{ $module['deliveryMode'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <i data-lucide="chevron-up" class="w-5 h-5 flex-shrink-0 ml-3 text-brand-gold" x-show="expanded === {{ $expandKey }}" x-cloak></i>
                        <i data-lucide="chevron-down" class="w-5 h-5 flex-shrink-0 ml-3 text-gray-400" x-show="expanded !== {{ $expandKey }}"></i>
                    </button>
                    <div x-show="expanded === {{ $expandKey }}" x-cloak class="border-t border-gray-200">
                        <div class="px-5 md:px-6 py-6 bg-gradient-to-b from-gray-50 to-white">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                                @foreach (($module['sections'] ?? []) as $section)
                                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                                        <h4 class="text-base font-bold text-brand-blue mb-3 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $color }}"></span>
                                            {{ $section['title'] }}
                                        </h4>
                                        <ul class="space-y-2">
                                            @foreach (($section['items'] ?? $section['topics'] ?? []) as $item)
                                                <li class="flex items-start gap-2 text-gray-700 text-sm">
                                                    <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5 flex-shrink-0" style="color: {{ $color }}"></i>
                                                    <span>{{ $item }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-4 border-t border-gray-200">
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold">Duration:</span> {{ $module['duration'] }} |
                                    <span class="font-semibold ml-2">Mode:</span> {{ $module['deliveryMode'] }}
                                </div>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <a href="https://wa.me/237675321739?text={{ urlencode('Hello, I would like to inquire about ' . $module['title']) }}"
                                       target="_blank" rel="noopener"
                                       class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold">
                                        <i data-lucide="message-circle" class="w-4 h-4"></i> Inquire
                                    </a>
                                    <a href="{{ url('/register-now') }}?module={{ urlencode($module['title']) }}"
                                       class="inline-flex items-center justify-center gap-2 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:scale-105 transition-transform"
                                       style="background-color: {{ $color }}">
                                        Register <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 text-center py-16 bg-white rounded-2xl border border-gray-200">
                    <h3 class="text-2xl font-bold text-brand-blue mb-2">No courses published yet</h3>
                    <p class="text-gray-600 max-w-md mx-auto">Active courses from Course Manager will appear here under Training.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>

@push('head')
<style>
    .program-card:hover {
        border-color: var(--program-color) !important;
        box-shadow: 0 10px 28px color-mix(in srgb, var(--program-color) 28%, transparent), 0 4px 12px rgba(0,0,0,.08);
        transform: translateY(-2px);
    }
    .program-card:hover .program-card-btn {
        background-color: color-mix(in srgb, var(--program-color) 10%, white);
    }
    .program-card:hover .program-card-icon {
        transform: scale(1.08);
    }
    .program-card:hover h3 {
        color: var(--program-color);
    }
</style>
@endpush

<section class="py-6 sm:py-8 bg-gradient-to-br from-brand-blue via-[#0052A3] to-brand-blue">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-4">Ready to Transform Your Career?</h2>
        <a href="{{ url('/register-now') }}" class="inline-block bg-brand-gold text-brand-blue hover:bg-[#C19B2A] px-8 py-3 text-base font-bold rounded-full shadow-lg hover:scale-105 transition-transform">
            Enroll Now
        </a>
    </div>
</section>

@endsection

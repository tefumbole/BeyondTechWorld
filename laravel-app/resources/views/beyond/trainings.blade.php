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

<section class="bg-gradient-to-br from-brand-blue via-[#0052A3] to-brand-blue pt-24 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-6">
            Professional <span class="text-brand-gold">IT Training</span>
        </h1>
        <p class="text-xl md:text-2xl text-blue-100 mb-4 max-w-4xl mx-auto">Master cutting-edge technologies with industry-leading programs</p>
        <p class="text-lg text-blue-200 mb-8 max-w-3xl mx-auto">Hands-on training in AI, Cloud, Security, Networking, and more — designed for 2026 and beyond</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mt-8">
            <a href="#programs" class="bg-brand-gold hover:bg-[#C19B2A] text-brand-blue px-8 py-4 text-lg font-bold shadow-lg hover:scale-105 transition-transform rounded-full">Explore Programs</a>
            <a href="{{ url('/register-now') }}" class="border-2 border-white text-white hover:bg-white hover:text-brand-blue px-8 py-4 text-lg font-bold shadow-lg hover:scale-105 transition-all rounded-full">Register Now</a>
        </div>
    </div>
</section>

<section class="py-12 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div><div class="text-4xl font-bold text-brand-blue mb-2">{{ count($programs) }}</div><div class="text-gray-600">Training Programs</div></div>
            <div><div class="text-4xl font-bold text-brand-blue mb-2">8-14</div><div class="text-gray-600">Weeks Duration</div></div>
            <div><div class="text-4xl font-bold text-brand-blue mb-2">100%</div><div class="text-gray-600">Hands-on Labs</div></div>
            <div><div class="text-4xl font-bold text-brand-blue mb-2">24/7</div><div class="text-gray-600">Support Access</div></div>
        </div>
    </div>
</section>

<section id="programs" class="py-20 bg-gradient-to-b from-gray-50 to-white" x-data="{ expanded: null }">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl md:text-5xl font-bold text-brand-blue mb-4">Training Programs</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Select a program to explore the comprehensive curriculum, tools, and career opportunities</p>
        </div>
        <div class="space-y-6">
            @foreach ($programs as $module)
                @php $icon = $iconMap[$module['icon'] ?? ''] ?? 'briefcase'; @endphp
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-shadow">
                    <button type="button" @click="expanded = expanded === {{ $module['id'] }} ? null : {{ $module['id'] }}"
                            class="w-full px-6 md:px-8 py-6 flex items-center justify-between hover:bg-gray-50 transition-colors text-left">
                        <div class="flex items-center space-x-4 md:space-x-6 flex-1 min-w-0">
                            <div class="p-4 rounded-xl flex-shrink-0" style="background-color: {{ $module['color'] }}15">
                                <i data-lucide="{{ $icon }}" class="w-8 h-8 md:w-10 md:h-10" style="color: {{ $module['color'] }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-xl md:text-2xl font-bold text-brand-blue mb-1">{{ $module['title'] }}</h3>
                                <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                                    <span class="font-semibold">{{ $module['duration'] }}</span>
                                    <span class="hidden sm:inline">•</span>
                                    <span class="hidden sm:inline">{{ $module['deliveryMode'] }}</span>
                                </div>
                            </div>
                        </div>
                        <i data-lucide="chevron-up" class="w-6 h-6 flex-shrink-0 ml-4 text-brand-gold" x-show="expanded === {{ $module['id'] }}" x-cloak></i>
                        <i data-lucide="chevron-down" class="w-6 h-6 flex-shrink-0 ml-4 text-gray-400" x-show="expanded !== {{ $module['id'] }}"></i>
                    </button>
                    <div x-show="expanded === {{ $module['id'] }}" x-cloak class="border-t border-gray-200">
                        <div class="px-6 md:px-8 py-8 bg-gradient-to-b from-gray-50 to-white">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                                @foreach (($module['sections'] ?? []) as $section)
                                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                                        <h4 class="text-lg font-bold text-brand-blue mb-4 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $module['color'] }}"></span>
                                            {{ $section['title'] }}
                                        </h4>
                                        <ul class="space-y-2.5">
                                            @foreach ($section['items'] as $item)
                                                <li class="flex items-start gap-3 text-gray-700 text-sm">
                                                    <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5 flex-shrink-0" style="color: {{ $module['color'] }}"></i>
                                                    <span>{{ $item }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 pt-6 border-t border-gray-200">
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold">Duration:</span> {{ $module['duration'] }} |
                                    <span class="font-semibold ml-2">Mode:</span> {{ $module['deliveryMode'] }}
                                </div>
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <a href="https://wa.me/237675321739?text={{ urlencode('Hello, I would like to inquire about ' . $module['title']) }}"
                                       target="_blank" rel="noopener"
                                       class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold">
                                        <i data-lucide="message-circle" class="w-4 h-4"></i> Inquire about {{ $module['title'] }}
                                    </a>
                                    <a href="{{ url('/register-now') }}?module={{ urlencode($module['title']) }}"
                                       class="inline-flex items-center justify-center gap-2 text-white px-6 py-3 rounded-lg font-semibold hover:scale-105 transition-transform"
                                       style="background-color: {{ $module['color'] }}">
                                        Register for {{ $module['title'] }} <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12"><h2 class="text-4xl font-bold text-brand-blue mb-4">Why Train With Us?</h2></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach ([
                ['Industry Experts', 'Learn from certified professionals with real-world experience'],
                ['Hands-On Labs', 'Practical training with real equipment and enterprise tools'],
                ['Career Support', 'Job placement assistance and certification preparation'],
            ] as [$title, $desc])
                <div class="text-center p-6 bg-gray-50 rounded-xl">
                    <div class="w-16 h-16 bg-brand-blue rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="check-circle-2" class="w-8 h-8 text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold text-brand-blue mb-2">{{ $title }}</h3>
                    <p class="text-gray-600">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-20 bg-gradient-to-br from-brand-blue via-[#0052A3] to-brand-blue relative overflow-hidden">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <h2 class="text-4xl md:text-5xl font-bold text-white mb-6">Ready to Transform Your Career?</h2>
        <p class="text-xl text-blue-100 mb-10 max-w-2xl mx-auto">Join thousands of professionals who have upgraded their skills with Beyond Enterprise</p>
        <a href="{{ url('/register-now') }}" class="inline-block bg-brand-gold text-brand-blue hover:bg-[#C19B2A] px-12 py-6 text-xl font-bold rounded-full shadow-2xl hover:scale-110 transition-transform">
            Enroll Now — Limited Seats Available
        </a>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-6 mt-8 text-blue-200 text-sm">
            <a href="mailto:info@beyondtechworld.com" class="hover:text-brand-gold">info@beyondtechworld.com</a>
            <span class="hidden sm:inline text-blue-400">•</span>
            <a href="tel:+237675321739" class="hover:text-brand-gold">+237 675 321 739</a>
        </div>
    </div>
</section>

@endsection

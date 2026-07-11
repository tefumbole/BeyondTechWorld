@extends('beyond.layout')

@section('title', 'About Beyond Enterprise | Our Vision & Team')
@section('meta_description', 'Learn about Beyond Enterprise — our history, mission, leadership, and commitment to technological excellence in Rwanda and beyond.')

@section('content')

<section class="relative py-20 bg-brand-blue text-white overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=2072&auto=format&fit=crop');"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl md:text-6xl font-bold mb-6">Bridging Technology & Innovation</h1>
        <p class="text-xl md:text-2xl text-gray-200 max-w-3xl mx-auto font-light">
            We are a premier IT consultancy and infrastructure firm dedicated to transforming businesses through cutting-edge technology solutions.
        </p>
    </div>
</section>

<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-bold text-brand-blue mb-6">Our Mission</h2>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                    To empower organizations in Africa and beyond with robust, scalable, and secure technology infrastructure. We strive to be the bridge that connects complex technological challenges with simple, effective, and sustainable solutions.
                </p>
                <div class="grid grid-cols-2 gap-6">
                    <div class="flex items-start gap-3">
                        <div class="bg-blue-100 p-2 rounded-lg"><i data-lucide="target" class="w-6 h-6 text-brand-blue"></i></div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Excellence</h3>
                            <p class="text-sm text-gray-500">World-class standards</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="bg-blue-100 p-2 rounded-lg"><i data-lucide="globe-2" class="w-6 h-6 text-brand-blue"></i></div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Global Reach</h3>
                            <p class="text-sm text-gray-500">International partnerships</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="relative rounded-2xl overflow-hidden shadow-2xl">
                <img src="https://horizons-cdn.hostinger.com/81ef3422-3855-479e-bfe8-28a4ceb0df39/513a28b3-47b7-490b-b30a-f9398973361b-a4hCG.png" alt="Team Collaboration" class="w-full h-full object-cover">
            </div>
        </div>
    </div>
</section>

<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            @foreach ([['15+','Years Experience'],['500+','Projects Completed'],['50+','Team Members'],['20+','Global Partners']] as [$value, $label])
                <div class="text-center">
                    <div class="text-4xl font-bold text-brand-blue mb-2">{{ $value }}</div>
                    <div class="text-gray-600 font-medium">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-20 bg-brand-blue">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-white mb-4">Our Leadership</h2>
        <div class="h-1 w-24 bg-brand-gold mx-auto"></div>
        <p class="mt-4 text-xl text-gray-300 mb-8">The visionaries driving Beyond Enterprise forward</p>
        <p class="text-gray-400 max-w-xl mx-auto">Leadership profiles will appear here once connected to the member directory.</p>
    </div>
</section>

<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-brand-blue mb-12">Our Core Values</h2>
        <div class="grid md:grid-cols-3 gap-8">
            @foreach ([
                ['users', 'Client First', 'We prioritize our clients\' needs and success above all else.'],
                ['award', 'Integrity', 'We conduct business with transparency, honesty, and ethical standards.'],
                ['briefcase', 'Innovation', 'We constantly evolve and adapt to the latest technological advancements.'],
            ] as [$icon, $title, $desc])
                <div class="p-6 bg-gray-50 rounded-xl hover:shadow-lg transition-shadow">
                    <i data-lucide="{{ $icon }}" class="w-12 h-12 text-brand-gold mx-auto mb-4"></i>
                    <h3 class="text-xl font-bold text-brand-blue mb-2">{{ $title }}</h3>
                    <p class="text-gray-600">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-16 bg-gradient-to-r from-brand-blue to-brand-dark text-white text-center">
    <div class="max-w-4xl mx-auto px-4">
        <h2 class="text-3xl font-bold mb-6">Ready to work with us?</h2>
        <p class="text-xl mb-8 opacity-90">Let's build something extraordinary together.</p>
        <a href="https://wa.me/237675321739" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 bg-brand-gold text-brand-blue font-bold text-lg px-8 py-4 rounded-full hover:bg-white hover:scale-105 transition-all">
            <i data-lucide="message-circle" class="w-5 h-5"></i> Chat on WhatsApp
        </a>
    </div>
</section>

@endsection

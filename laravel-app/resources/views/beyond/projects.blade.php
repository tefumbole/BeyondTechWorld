@extends('beyond.layout')

@section('title', 'Our Projects')
@section('meta_description', 'Explore our recent projects in IT consultancy, networking, and audio-visual installations across Kigali, Rwanda.')

@section('content')

@include('beyond.partials.hero', [
    'title' => \App\Support\SiteContent::html('projects.hero_title', 'Our <span class="text-brand-gold">Projects</span>'),
    'subtitle' => \App\Support\SiteContent::text('projects.hero_subtitle', 'See our engineering precision in action'),
])

<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach ($projects as $project)
                @php
                    $videoId = preg_match('/video\/(\d+)/', $project['url'], $m) ? $m[1] : null;
                @endphp
                <div class="bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 p-4 h-full flex flex-col">
                    <div class="flex-grow flex items-center justify-center bg-gray-100 rounded-lg mb-4 min-h-[500px] relative overflow-hidden">
                        @if ($videoId)
                            <blockquote class="tiktok-embed" cite="{{ $project['url'] }}" data-video-id="{{ $videoId }}" style="max-width:605px;min-width:325px;">
                                <section>
                                    <a target="_blank" rel="noopener noreferrer" href="{{ $project['url'] }}" title="{{ $project['title'] }}" class="text-brand-light hover:underline">{{ $project['title'] }}</a>
                                </section>
                            </blockquote>
                        @else
                            <a href="{{ $project['url'] }}" target="_blank" rel="noopener" class="text-brand-light font-semibold">View on TikTok</a>
                        @endif
                    </div>
                    <div class="mt-4">
                        <h3 class="text-lg font-bold text-brand-blue mb-2">{{ $project['title'] }}</h3>
                        <a href="{{ $project['url'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center text-sm text-brand-gold hover:text-brand-blue transition-colors font-medium">
                            Watch on TikTok <i data-lucide="external-link" class="w-4 h-4 ml-1"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-16 bg-white border-t border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-brand-blue mb-6">Inspired by our work?</h2>
        <p class="text-lg text-gray-700 mb-8">Let's discuss how we can bring the same level of quality to your next project.</p>
        <a href="https://wa.me/237675321739?text={{ urlencode('Hello Beyond Enterprise, I would like to start a project.') }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 bg-brand-gold text-brand-blue px-8 py-4 text-lg font-bold rounded-lg shadow-xl">
            <i data-lucide="message-circle" class="w-5 h-5"></i> Start Your Project
        </a>
    </div>
</section>

@endsection

@push('scripts')
<script async src="https://www.tiktok.com/embed.js"></script>
@endpush

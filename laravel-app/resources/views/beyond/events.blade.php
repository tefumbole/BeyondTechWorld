@extends('beyond.layout')

@section('title', 'Events & Highlights')
@section('meta_description', 'Discover upcoming events, workshops, and highlights from Beyond Enterprise.')

@section('content')

@if (count($events) === 0)
    <div class="min-h-[60vh] flex items-center justify-center bg-gray-50 p-4">
        <div class="text-center max-w-md">
            <i data-lucide="calendar" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">No Events Yet</h2>
            <p class="text-gray-600">Check back soon for upcoming events and highlights from Beyond Enterprise.</p>
        </div>
    </div>
@else
    <div class="min-h-screen bg-gray-50 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-brand-blue mb-4">Events & Highlights</h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Join us at our upcoming events, workshops, and technology showcases</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach ($events as $event)
                    <a href="{{ url('/events/' . $event['id']) }}" class="group block bg-white rounded-xl border-2 border-gray-200 hover:border-brand-blue transition-all hover:shadow-xl overflow-hidden">
                        <div class="relative h-48 overflow-hidden bg-gray-200">
                            @if (!empty($event['featured_image']))
                                <img src="{{ $event['featured_image'] }}" alt="{{ $event['title'] }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-blue to-brand-light">
                                    <i data-lucide="calendar" class="w-16 h-16 text-white opacity-50"></i>
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 bg-brand-gold text-brand-blue font-bold px-3 py-1 rounded-full text-xs">
                                {{ \Carbon\Carbon::parse($event['event_date'])->format('M d') }}
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-gray-900 mb-3 line-clamp-2 group-hover:text-brand-blue">{{ $event['title'] }}</h3>
                            <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                <i data-lucide="calendar" class="w-4 h-4 text-brand-blue"></i>
                                <span>{{ \Carbon\Carbon::parse($event['event_date'])->format('l, F d, Y') }}</span>
                            </div>
                            @if (!empty($event['location']))
                                <div class="flex items-center gap-2 text-sm text-gray-600 mb-3">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-brand-blue"></i>
                                    <span class="line-clamp-1">{{ $event['location'] }}</span>
                                </div>
                            @endif
                            <div class="flex items-center gap-2 text-brand-blue font-semibold text-sm">
                                <span>View Details</span>
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif

@endsection

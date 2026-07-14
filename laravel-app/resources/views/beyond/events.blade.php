@extends('beyond.layout')

@section('title', 'Events & Highlights')
@section('meta_description', 'Discover upcoming events, workshops, and highlights from Beyond Enterprise.')

@section('content')

@include('beyond.partials.hero', [
    'title' => 'Events & <span class="text-brand-gold">Highlights</span>',
    'subtitle' => 'Join us at upcoming events, workshops, and technology showcases across Rwanda',
])

<section class="py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Filters --}}
        <form method="GET" action="{{ url('/events') }}" class="mb-8 flex flex-col md:flex-row gap-4 items-stretch md:items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search events…"
                       class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-brand-blue focus:border-brand-blue">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter</label>
                <select name="filter" class="rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-brand-blue">
                    @foreach(['upcoming' => 'Upcoming', 'featured' => 'Featured', 'ongoing' => 'Ongoing', 'past' => 'Past'] as $k => $label)
                        <option value="{{ $k }}" {{ $filter === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="rounded-lg border border-gray-300 px-4 py-2">
                    <option value="">All types</option>
                    @foreach(\App\Event::TYPES as $k => $label)
                        <option value="{{ $k }}" {{ request('type') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-6 py-2 bg-brand-blue text-white font-semibold rounded-lg hover:bg-brand-dark transition">Search</button>
        </form>

        @if($featured->isNotEmpty() && $filter === 'upcoming' && !request()->hasAny(['q', 'type']))
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-brand-blue mb-6">Featured Events</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($featured as $fe)
                        @php
                            $fp = $fe->publication;
                            $fflyer = $pubService->publicFlyerUrl($fe, $fp);
                            $fstatus = $pubService->computePublicStatus($fe, $fp);
                        @endphp
                        <a href="{{ url('/events/' . $fe->slug) }}" class="group block bg-white rounded-xl border-2 border-brand-gold/40 hover:border-brand-gold transition-all hover:shadow-xl overflow-hidden">
                            <div class="relative h-40 overflow-hidden bg-gray-200">
                                @if($fflyer)
                                    <img src="{{ $fflyer }}" alt="{{ $fp->public_title ?: $fe->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-blue to-brand-light">
                                        <i data-lucide="star" class="w-12 h-12 text-brand-gold opacity-80"></i>
                                    </div>
                                @endif
                                <span class="absolute top-2 left-2 bg-brand-gold text-brand-blue text-xs font-bold px-2 py-1 rounded-full">Featured</span>
                            </div>
                            <div class="p-4">
                                <h3 class="font-bold text-gray-900 line-clamp-2 group-hover:text-brand-blue">{{ $fp->public_title ?: $fe->name }}</h3>
                                @if($fe->event_start_at)
                                    <p class="text-sm text-gray-500 mt-1">{{ $fe->event_start_at->format('M d, Y') }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($events->isEmpty())
            <div class="text-center py-20">
                <i data-lucide="calendar" class="w-16 h-16 text-gray-300 mx-auto mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">No Events Found</h2>
                <p class="text-gray-600">Try a different filter or check back soon for new events.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach ($events as $row)
                    @php
                        $ev = $row['event'];
                        $pub = $row['pub'];
                        $flyer = $row['flyer'];
                        $status = $row['public_status'];
                        $statusLabels = \App\Services\EventPublicationService::PUBLIC_STATUSES;
                        $statusColors = [
                            'coming_soon' => 'bg-blue-100 text-blue-800',
                            'setup_in_progress' => 'bg-amber-100 text-amber-800',
                            'happening_today' => 'bg-green-100 text-green-800',
                            'event_in_progress' => 'bg-yellow-100 text-yellow-900',
                            'completed' => 'bg-gray-200 text-gray-700',
                            'postponed' => 'bg-orange-100 text-orange-800',
                            'cancelled' => 'bg-red-100 text-red-800',
                        ];
                    @endphp
                    <a href="{{ url('/events/' . $ev->slug) }}" class="group block bg-white rounded-xl border border-gray-200 hover:border-brand-blue transition-all hover:shadow-xl overflow-hidden">
                        <div class="relative h-48 overflow-hidden bg-gray-200">
                            @if ($flyer)
                                <img src="{{ $flyer }}" alt="{{ $pub->public_title ?: $ev->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-blue to-brand-light">
                                    <i data-lucide="calendar" class="w-16 h-16 text-white opacity-50"></i>
                                </div>
                            @endif
                            @if($ev->event_start_at)
                                <div class="absolute top-3 right-3 bg-brand-gold text-brand-blue font-bold px-3 py-1 rounded-full text-xs">
                                    {{ $ev->event_start_at->format('M d') }}
                                </div>
                            @endif
                            @if($status)
                                <span class="absolute bottom-3 left-3 text-xs font-semibold px-2 py-1 rounded-full {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $statusLabels[$status] ?? $status }}
                                </span>
                            @endif
                        </div>
                        <div class="p-5">
                            <p class="text-xs text-brand-blue font-medium mb-1">{{ \App\Event::TYPES[$ev->event_type] ?? $ev->event_type }}</p>
                            <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2 group-hover:text-brand-blue">{{ $pub->public_title ?: $ev->name }}</h3>
                            @if($pub->public_summary)
                                <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ $pub->public_summary }}</p>
                            @endif
                            @if ($ev->event_start_at)
                                <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                    <i data-lucide="calendar" class="w-4 h-4 text-brand-blue"></i>
                                    <span>{{ $ev->event_start_at->format('l, F d, Y') }}</span>
                                </div>
                            @endif
                            @if (!empty($pub->public_location) || !empty($pub->public_venue))
                                <div class="flex items-center gap-2 text-sm text-gray-600 mb-3">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-brand-blue"></i>
                                    <span class="line-clamp-1">{{ $pub->public_venue ?: $pub->public_location }}</span>
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
        @endif
    </div>
</section>

@endsection

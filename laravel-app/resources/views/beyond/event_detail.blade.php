@extends('beyond.layout')

@section('title', ($pub->public_title ?: $event->name) . ' | Events')
@section('meta_description', $pub->public_summary ?: 'Event details from Beyond Enterprise.')

@section('content')

@php
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
    $title = $pub->public_title ?: $event->name;
@endphp

{{-- Hero with flyer --}}
<section class="relative bg-brand-navy text-white overflow-hidden">
    @if($flyer)
        <div class="absolute inset-0">
            <img src="{{ $flyer }}" alt="" class="w-full h-full object-cover opacity-30">
            <div class="absolute inset-0 bg-gradient-to-t from-brand-navy via-brand-navy/80 to-brand-navy/40"></div>
        </div>
    @endif
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 md:py-24 text-center">
        <p class="text-brand-gold font-semibold tracking-wide uppercase text-sm mb-3">{{ \App\Event::TYPES[$event->event_type] ?? $event->event_type }}</p>
        <h1 class="text-3xl md:text-5xl font-bold mb-4">{{ $title }}</h1>
        @if($publicStatus)
            <span class="inline-block text-sm font-semibold px-4 py-1 rounded-full {{ $statusColors[$publicStatus] ?? 'bg-white/20 text-white' }}">
                {{ $statusLabels[$publicStatus] ?? $publicStatus }}
            </span>
        @endif
        @if($pub->public_summary)
            <p class="mt-6 text-lg text-gray-200 max-w-2xl mx-auto">{{ $pub->public_summary }}</p>
        @endif
    </div>
</section>

@if ($countdownAt && $pub->show_countdown)
    @include('beyond.partials.event_countdown', [
        'targetIso' => $countdownAt->toIso8601String(),
        'timezone' => $event->timezone ?: 'Africa/Kigali',
        'completionMessage' => $pub->countdown_completion_message ?: 'The event is here!',
        'hideAfter' => $pub->hide_countdown_after_completion,
    ])
@endif

<section class="py-12 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            <div class="lg:col-span-2">
                @if($pub->public_announcement)
                    <div class="mb-8 p-4 bg-amber-50 border-l-4 border-brand-gold rounded-r-lg">
                        <p class="font-semibold text-brand-blue mb-1">Announcement</p>
                        <p class="text-gray-700">{{ $pub->public_announcement }}</p>
                    </div>
                @endif

                @if($pub->public_description)
                    <div class="prose prose-lg max-w-none text-gray-700 event-public-description">
                        {!! $pub->public_description !!}
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <h3 class="font-bold text-brand-blue mb-4">Event Details</h3>
                    <ul class="space-y-4 text-sm">
                        @if($pub->show_event_time && $event->event_start_at)
                            <li class="flex gap-3">
                                <i data-lucide="calendar" class="w-5 h-5 text-brand-blue shrink-0"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Starts</p>
                                    <p class="text-gray-600">{{ $event->event_start_at->timezone($event->timezone ?: 'Africa/Kigali')->format('l, F j, Y g:i A') }}</p>
                                </div>
                            </li>
                        @endif
                        @if($pub->show_event_time && $event->event_end_at)
                            <li class="flex gap-3">
                                <i data-lucide="clock" class="w-5 h-5 text-brand-blue shrink-0"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Ends</p>
                                    <p class="text-gray-600">{{ $event->event_end_at->timezone($event->timezone ?: 'Africa/Kigali')->format('l, F j, Y g:i A') }}</p>
                                </div>
                            </li>
                        @endif
                        @if($pub->show_setup_info && $event->setup_start_at)
                            <li class="flex gap-3">
                                <i data-lucide="wrench" class="w-5 h-5 text-brand-blue shrink-0"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Setup</p>
                                    <p class="text-gray-600">{{ $event->setup_start_at->timezone($event->timezone ?: 'Africa/Kigali')->format('M j, Y g:i A') }}</p>
                                </div>
                            </li>
                        @endif
                        @if($pub->public_venue || $pub->public_location)
                            <li class="flex gap-3">
                                <i data-lucide="map-pin" class="w-5 h-5 text-brand-blue shrink-0"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Location</p>
                                    @if($pub->public_venue)<p class="text-gray-600">{{ $pub->public_venue }}</p>@endif
                                    @if($pub->public_location)<p class="text-gray-500">{{ $pub->public_location }}</p>@endif
                                </div>
                            </li>
                        @endif
                        @if($pub->public_contact_name || $pub->public_contact_phone)
                            <li class="flex gap-3">
                                <i data-lucide="phone" class="w-5 h-5 text-brand-blue shrink-0"></i>
                                <div>
                                    <p class="font-medium text-gray-900">Contact</p>
                                    @if($pub->public_contact_name)<p class="text-gray-600">{{ $pub->public_contact_name }}</p>@endif
                                    @if($pub->public_contact_phone)<p class="text-gray-600">{{ $pub->public_contact_phone }}</p>@endif
                                    @if($pub->public_contact_email)<p class="text-gray-600">{{ $pub->public_contact_email }}</p>@endif
                                </div>
                            </li>
                        @endif
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    @if($pub->registration_url)
                        <a href="{{ $pub->registration_url }}" target="_blank" rel="noopener"
                           class="block text-center px-6 py-3 bg-brand-blue text-white font-semibold rounded-lg hover:bg-brand-dark transition">
                            Register Now
                        </a>
                    @endif
                    @if($pub->ticket_url)
                        <a href="{{ $pub->ticket_url }}" target="_blank" rel="noopener"
                           class="block text-center px-6 py-3 border-2 border-brand-blue text-brand-blue font-semibold rounded-lg hover:bg-brand-blue hover:text-white transition">
                            Get Tickets
                        </a>
                    @endif
                    @if($pub->external_url)
                        <a href="{{ $pub->external_url }}" target="_blank" rel="noopener"
                           class="block text-center px-6 py-3 text-brand-blue font-medium hover:underline">
                            More information ↗
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@if($related->isNotEmpty())
<section class="py-12 bg-gray-50 border-t">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-brand-blue mb-8">Related Events</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($related as $rel)
                @php $rp = $rel->publication; @endphp
                <a href="{{ url('/events/' . $rel->slug) }}" class="block bg-white rounded-xl border hover:border-brand-blue overflow-hidden hover:shadow-lg transition">
                    @if($pubService->publicFlyerUrl($rel, $rp))
                        <img src="{{ $pubService->publicFlyerUrl($rel, $rp) }}" alt="" class="w-full h-36 object-cover">
                    @endif
                    <div class="p-4">
                        <h3 class="font-bold text-gray-900">{{ $rp->public_title ?: $rel->name }}</h3>
                        @if($rel->event_start_at)
                            <p class="text-sm text-gray-500 mt-1">{{ $rel->event_start_at->format('M d, Y') }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

<div class="py-8 text-center">
    <a href="{{ url('/events') }}" class="text-brand-blue font-semibold hover:underline">← All events</a>
</div>

@endsection

@extends('layout.main')

@section('content')
@include('events.partials.modern')
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-page-head">
            <div>
                <div class="ev-kicker">Events</div>
                <h4>Event Calendar</h4>
                <p>Gold is setup. The event colour is the event itself. Green is unpacking.</p>
            </div>
        </div>
        <div class="ev-cal">
            <div class="ev-cal-toolbar">
                <a class="ev-cal-nav" href="{{ route('events.calendar', ['month' => $prevMonth]) }}" aria-label="Previous month">‹</a>
                <h3>{{ $monthLabel }}</h3>
                <a class="ev-cal-nav" href="{{ route('events.calendar', ['month' => $nextMonth]) }}" aria-label="Next month">›</a>
            </div>
            <div class="ev-cal-legend">
                <span><i style="background:#c6ab47"></i> Setup</span>
                <span><i style="background:#0b3f90"></i> Event</span>
                <span><i style="background:#0f766e"></i> Unpacking</span>
            </div>
            <div class="ev-cal-scroll">
                <div class="ev-cal-grid">
                    <div class="ev-cal-weekdays">
                        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)
                            <span>{{ $weekday }}</span>
                        @endforeach
                    </div>
                    @foreach($weeks as $week)
                        <div class="ev-cal-week">
                            <div class="ev-cal-days">
                                @foreach($week['days'] as $day)
                                    <div class="ev-cal-day {{ $day->format('Y-m') === $monthKey ? '' : 'is-out' }} {{ $day->toDateString() === $today ? 'is-today' : '' }}">
                                        <span class="num">{{ $day->format('j') }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="ev-cal-bars" style="height: {{ $week['lanes'] * 28 }}px;">
                                @foreach($week['bars'] as $bar)
                                    <a class="ev-bar is-{{ $bar['kind'] ?? 'event' }} {{ $bar['continues_before'] ? 'is-continued-before' : '' }} {{ $bar['continues_after'] ? 'is-continued-after' : '' }}"
                                       href="{{ route('events.show', $bar['id']) }}"
                                       title="{{ $bar['name'] }}"
                                       style="background: {{ $bar['color'] }}; top: {{ $bar['lane'] * 28 }}px; left: calc({{ $bar['col'] }} * 14.2857% + 3px); width: calc({{ $bar['span'] }} * 14.2857% - 6px);">
                                        {{ $bar['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

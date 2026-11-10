@extends('layout.main')

@section('content')
@include('events.partials.modern')
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-page-head">
            <div>
                <div class="ev-kicker">Events</div>
                <h4>Event Calendar</h4>
                <p>{{ $intro }}</p>
            </div>
        </div>
        @php
            $split = in_array($tab, ['setup', 'unpacking'], true);
            $laneStep = $split ? 38 : 18;
        @endphp
        <div class="ev-cal is-{{ $tab }} {{ $split ? 'is-split' : '' }} {{ in_array($tab, ['engineers', 'payments', 'setup', 'unpacking'], true) ? 'is-detail' : '' }}">
            <nav class="ev-cal-tabs">
                @foreach([
                    'event' => ['Event Date', '#e11d48'],
                    'setup' => ['Setup', '#f97316'],
                    'unpacking' => ['Unpacking', '#06b6d4'],
                    'engineers' => ['Engineers', '#7c3aed'],
                    'payments' => ['Payments', '#059669'],
                ] as $key => $meta)
                    <a class="is-{{ $key }} {{ $tab === $key ? 'is-on' : '' }}" href="{{ route('events.calendar', ['month' => $monthKey, 'tab' => $key]) }}">
                        <i style="background:{{ $tab === $key ? 'rgba(255,255,255,.9)' : $meta[1] }}"></i>{{ $meta[0] }}
                    </a>
                @endforeach
            </nav>
            <div class="ev-cal-toolbar">
                <a class="ev-cal-nav" href="{{ route('events.calendar', ['month' => $prevMonth, 'tab' => $tab]) }}" aria-label="Previous month">‹</a>
                <h3>{{ $monthLabel }}</h3>
                <a class="ev-cal-nav" href="{{ route('events.calendar', ['month' => $nextMonth, 'tab' => $tab]) }}" aria-label="Next month">›</a>
            </div>
            <div class="ev-cal-legend">
                @if($tab === 'setup')
                    <span><i style="background:#f97316"></i> Setup dates only</span>
                @elseif($tab === 'unpacking')
                    <span><i style="background:#06b6d4"></i> Unpacking dates only</span>
                @elseif($tab === 'engineers')
                    <span><i style="background:#7c3aed"></i> Engineer on that day</span>
                @elseif($tab === 'payments')
                    <span><i style="background:#059669"></i> Total paid that day</span>
                @else
                    <span><b class="ev-cal-date-key"></b> Date of event</span>
                    <span><i style="background:#f97316"></i> Setup</span>
                    <span><i style="background:#0b3f90"></i> Event</span>
                    <span><i style="background:#06b6d4"></i> Unpacking</span>
                @endif
            </div>
            <div class="ev-cal-scroll">
                <div class="ev-cal-grid">
                    <div class="ev-cal-weekdays">
                        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)
                            <span>{{ $weekday }}</span>
                        @endforeach
                    </div>
                    @foreach($weeks as $week)
                        @php
                            $eventDays = [];
                            foreach ($week['bars'] as $bar) {
                                $start = (int) $bar['col'];
                                $span = max(1, (int) $bar['span']);
                                for ($i = $start; $i < $start + $span && $i < 7; $i++) {
                                    $eventDays[$i] = true;
                                }
                            }
                        @endphp
                        <div class="ev-cal-week" style="--lanes: {{ (int) $week['lanes'] }};">
                            @foreach($week['days'] as $index => $day)
                                <div class="ev-cal-cell {{ $day->format('Y-m') === $monthKey ? '' : 'is-out' }} {{ $day->toDateString() === $today ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }} {{ ! empty($eventDays[$index]) ? 'has-event' : '' }}">
                                    <span class="num">{{ $day->format('j') }}</span>
                                </div>
                            @endforeach
                            <div class="ev-cal-bars" style="height: {{ max($laneStep, $week['lanes'] * $laneStep) }}px;">
                                @foreach($week['bars'] as $bar)
                                    <a class="ev-bar is-{{ $bar['kind'] ?? 'event' }} {{ $bar['continues_before'] ? 'is-continued-before' : '' }} {{ $bar['continues_after'] ? 'is-continued-after' : '' }}"
                                       href="{{ $bar['id'] !== '' ? route('events.show', $bar['id']) : route('events.calendar', ['month' => $monthKey, 'tab' => $tab]) }}"
                                       title="{{ $bar['name'] }}"
                                       style="background: {{ $bar['color'] }}; top: {{ $bar['lane'] * $laneStep }}px; left: calc({{ $bar['col'] }} * 14.2857% + 3px); width: calc({{ $bar['span'] }} * 14.2857% - 6px);">
                                        @if($split && ! empty($bar['kicker']))
                                            <span class="ev-bar-kicker">{{ $bar['kicker'] }}</span>
                                            <span class="ev-bar-title">{{ $bar['event'] !== '' ? $bar['event'] : $bar['name'] }}</span>
                                        @else
                                            {{ $bar['name'] }}
                                        @endif
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

@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Routing diagram</h3>
    @include('mix.admin.nav')
    @if(!$version)
        <div class="alert alert-info">Save a mix template to draw its routing.</div>
    @else
        <p>{{ $template->name }}, version {{ $version->version_number }}. Channels feed the bus named on the channel. Buses stay in their own type.</p>
        <svg viewBox="0 0 760 420" style="width:100%;max-width:860px;background:#121820;border-radius:16px">
            @php $feeds = []; @endphp
            @foreach($version->channels as $channel)
                @if($channel->bus)
                    @php $feeds[$channel->bus->name][] = $channel; @endphp
                @endif
            @endforeach
            @php $busNames = array_keys($feeds); $y = 36; @endphp
            @foreach($version->channels as $i => $channel)
                @if($i > 15) @continue @endif
                <text x="16" y="{{ 28 + $i * 22 }}" fill="#d5deea" font-size="12">{{ $channel->position }} {{ $channel->name }}</text>
            @endforeach
            @foreach($busNames as $n => $name)
                <rect x="280" y="{{ 20 + $n * 48 }}" width="180" height="32" rx="8" fill="#1d4e89"></rect>
                <text x="292" y="{{ 41 + $n * 48 }}" fill="#fff" font-size="13">{{ $name }}</text>
            @endforeach
            <rect x="540" y="160" width="180" height="40" rx="8" fill="#0f6b4c"></rect>
            <text x="552" y="185" fill="#fff" font-size="13">Main / outputs</text>
            <text x="16" y="400" fill="#8ea0b8" font-size="12">Showing the first 16 channel names. Buses only appear when a channel is assigned to one.</text>
        </svg>
        <ul class="mt-3">
            @foreach($buses as $bus)
                <li>{{ $bus->name }} — {{ $bus->bus_type }} @if($bus->limiter_threshold_db !== null) · limiter {{ $bus->limiter_threshold_db }} dB @endif</li>
            @endforeach
        </ul>
    @endif
</div></section>
@endsection

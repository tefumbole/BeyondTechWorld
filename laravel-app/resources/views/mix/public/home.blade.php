@extends('mix.public.layout')
@section('title', 'Mix & Master')
@section('content')
<p class="muted">{{ $intro }}</p>
<form class="search" method="GET" action="{{ route('mix.home') }}">
    <input name="q" value="{{ $q }}" placeholder="Kick Drum, Snare Top, Drum Bus, Male Vocal, Piano, Live Gospel Mix">
    <button type="submit">Search</button>
</form>
<div class="chips">
    @foreach(['drums' => 'Drums', 'keys' => 'Keys', 'vocals' => 'Vocals', 'guitars' => 'Guitars', 'other' => 'Other', 'buses' => 'Buses'] as $slug => $label)
        <a href="{{ route('mix.home', ['category' => $slug]) }}" @if($category === $slug) style="background:#1d6fd6;color:#fff" @endif>{{ $label }}</a>
    @endforeach
</div>
@if($selected)
    <h2>{{ $selected->name }}</h2>
    <div class="grid">
        @foreach($instruments as $instrument)
            @php $saved = $instrument->presets->where('published', true); @endphp
            @if($saved->count())
                @foreach($saved as $preset)
                    <a class="card" href="{{ route('mix.public.preset', $preset->slug) }}">
                        <span class="tag">{{ $preset->sourceName() }}</span>
                        <h3>{{ $instrument->name }}</h3>
                        <div class="muted">{{ $preset->name }}</div>
                        @include('mix.public.preset_brief', ['version' => $preset->currentVersion])
                    </a>
                @endforeach
            @else
                <div class="card">
                    <h3>{{ $instrument->name }}</h3>
                    <div class="muted">No saved settings yet.</div>
                </div>
            @endif
        @endforeach
    </div>
@else
<h2>Featured Beyond presets</h2>
<div class="grid">
    @forelse($presets->where('collection', 'beyond') as $preset)
        <a class="card" href="{{ route('mix.public.preset', $preset->slug) }}">
            <span class="tag">Beyond</span>
            <h3>{{ $preset->name }}</h3>
            <div class="muted">{{ optional($preset->instrument)->name }} · {{ optional($preset->console)->name }}</div>
        </a>
    @empty
        <p class="muted">Published Beyond presets will appear here.</p>
    @endforelse
</div>
@endif
<h2>PreSonus preset collection</h2>
<div class="card">
    <p>Factory Fat Channel presets are not hosted here. PreSonus provides them to mixer owners from the official restore article.</p>
    <p><a href="https://support.presonus.com/hc/en-us/articles/360050575871-StudioLive-Series-III-Lost-Missing-PreSonus-FAT-Channel-Presets">StudioLive Series III Fat Channel presets</a></p>
    @foreach($presets->where('collection', 'presonus') as $preset)
        <div><a href="{{ route('mix.public.preset', $preset->slug) }}">{{ $preset->name }}</a></div>
    @endforeach
</div>
<h2>Complete mix templates</h2>
<div class="grid">
    @forelse($templates as $template)
        <a class="card" href="{{ route('mix.public.template', $template->slug) }}"><h3>{{ $template->name }}</h3><div class="muted">{{ optional($template->console)->name }} · {{ $template->channel_count }} channels</div></a>
    @empty
        <p class="muted">No public mix templates yet.</p>
    @endforelse
</div>
<h2>Show templates</h2>
<div class="grid">
    @forelse($shows as $show)
        <a class="card" href="{{ route('mix.public.show', $show->slug) }}"><h3>{{ $show->name }}</h3><div class="muted">{{ $show->event_type }}</div></a>
    @empty
        <p class="muted">No public show templates yet.</p>
    @endforelse
</div>
<h2>Mastering presets</h2>
<div class="grid">
    @forelse($mastering as $row)
        <a class="card" href="{{ route('mix.public.mastering', $row->slug) }}"><h3>{{ $row->name }}</h3><div class="muted">{{ $row->target_lufs !== null ? $row->target_lufs.' LUFS' : 'Reference chain' }}</div></a>
    @empty
        <p class="muted">No public mastering presets yet.</p>
    @endforelse
</div>
@endsection

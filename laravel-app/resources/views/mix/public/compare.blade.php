@extends('mix.public.layout')
@section('title', 'Compare presets')
@section('content')
<h1>Compare</h1>
<form class="search" method="GET">
    <select name="a" style="flex:1;background:#171e29;color:#fff;border-radius:12px;padding:12px">
        <option value="">First preset</option>
        @foreach($choices as $choice)<option value="{{ $choice->slug }}" {{ $left && $left->slug === $choice->slug ? 'selected' : '' }}>{{ $choice->name }}</option>@endforeach
    </select>
    <select name="b" style="flex:1;background:#171e29;color:#fff;border-radius:12px;padding:12px">
        <option value="">Second preset</option>
        @foreach($choices as $choice)<option value="{{ $choice->slug }}" {{ $right && $right->slug === $choice->slug ? 'selected' : '' }}>{{ $choice->name }}</option>@endforeach
    </select>
    <button type="submit">Compare</button>
</form>
<div class="grid">
    @foreach([$left, $right] as $preset)
        <div class="card">
            @if($preset && $preset->currentVersion)
                <h3>{{ $preset->name }}</h3>
                <p class="muted">{{ $preset->sourceName() }} · v{{ $preset->currentVersion->version_number }}</p>
                @include('mix.admin.version_summary', ['version' => $preset->currentVersion])
            @else
                <p class="muted">Choose a published preset.</p>
            @endif
        </div>
    @endforeach
</div>
@endsection

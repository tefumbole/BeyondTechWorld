@extends('mix.public.layout')
@section('title', $preset->name)
@section('content')
<p><a href="{{ route('mix.home') }}">All presets</a></p>
<span class="tag">{{ $preset->sourceName() }}</span>
<h1>{{ $preset->name }}</h1>
<p class="muted">{{ optional(optional($preset->instrument)->category)->name }} · {{ optional($preset->instrument)->name }} · {{ optional($preset->console)->name ?: 'Console not limited' }} · version {{ $version->version_number }}</p>
<div class="actions">
    <button class="btn" type="button" data-copy-target="mix-copy">Copy settings</button>
    <button class="btn" type="button" onclick="window.print()">Print</button>
    <button class="btn" type="button" data-copy-target="mix-share">Share</button>
    <a class="btn" href="{{ route('mix.public.compare', ['a' => $preset->slug]) }}">Compare</a>
    <a class="btn" href="{{ route('mix.public.preset.pdf', $preset->slug) }}">Download PDF</a>
</div>
<textarea id="mix-copy" readonly style="position:absolute;left:-9999px">{{ $copy }}</textarea>
<input id="mix-share" type="hidden" value="{{ route('mix.public.preset', $preset->slug) }}">
<div data-mix-readonly data-mix="@json($graph)">
    <h2>EQ</h2>
    <svg data-graph="eq"></svg>
    <h2>Compression</h2>
    <svg data-graph="compressor"></svg>
    <h2>Gate</h2>
    <svg data-graph="gate"></svg>
</div>
@include('mix.admin.version_summary', ['version' => $version])
@if($preset->notes)<h2>Notes</h2><p>{{ $preset->notes }}</p>@endif
<p class="muted">Reference settings. This page does not load the mixer.</p>
@endsection

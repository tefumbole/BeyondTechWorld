@extends('mix.public.layout')
@section('title', $template->name)
@section('content')
<h1>{{ $template->name }}</h1>
<p class="muted">{{ optional($template->console)->name }} · version {{ $version->version_number }} · {{ $template->channel_count }} channels on this sheet</p>
<div class="actions">
    <button class="btn" type="button" onclick="window.print()">Print</button>
    <a class="btn" href="{{ route('mix.public.template.pdf', $template->slug) }}">Download PDF</a>
</div>
<table>
    <thead><tr><th>Ch</th><th>Name</th><th>Preset version</th><th>Bus</th></tr></thead>
    <tbody>
    @foreach($version->channels as $channel)
        <tr>
            <td>{{ $channel->position }}</td>
            <td>{{ $channel->name }}</td>
            <td>@if($channel->presetVersion){{ optional($channel->presetVersion->preset)->name }} v{{ $channel->presetVersion->version_number }}@else — @endif</td>
            <td>{{ optional($channel->bus)->name ?: '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="muted">{{ $template->notes }}</p>
@endsection

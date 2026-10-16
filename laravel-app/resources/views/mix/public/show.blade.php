@extends('mix.public.layout')
@section('title', $show->name)
@section('content')
<h1>{{ $show->name }}</h1>
<p class="muted">{{ $show->event_type }} · {{ optional($show->console)->name }} · {{ $show->version_label }}</p>
<p>This sheet is the saved template version. It is a setup reference and is not sent to the mixer.</p>
@if($show->main_notes)<h2>Main L/R</h2><p>{{ $show->main_notes }}</p>@endif
@if($show->monitor_notes)<h2>Monitors</h2><p>{{ $show->monitor_notes }}</p>@endif
@if($show->subwoofer_notes)<h2>Subwoofer</h2><p>{{ $show->subwoofer_notes }}</p>@endif
@if($show->bus_notes)<h2>Buses</h2><p>{{ $show->bus_notes }}</p>@endif
@if($version)
    <h2>{{ optional($version->template)->name }} · version {{ $version->version_number }}</h2>
    <table>
        <thead><tr><th>Ch</th><th>Name</th><th>Preset</th><th>Bus</th></tr></thead>
        <tbody>
        @foreach($version->channels as $channel)
            <tr>
                <td>{{ $channel->position }}</td>
                <td>{{ $channel->name }}</td>
                <td>@if($channel->presetVersion){{ optional($channel->presetVersion->preset)->name }} v{{ $channel->presetVersion->version_number }}@endif</td>
                <td>{{ optional($channel->bus)->name }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
<p>{{ $show->notes }}</p>
<div class="actions"><button class="btn" type="button" onclick="window.print()">Print</button></div>
@endsection

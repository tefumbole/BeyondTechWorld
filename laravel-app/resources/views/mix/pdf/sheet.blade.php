<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 18px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border-bottom: 1px solid #ccc; text-align: left; padding: 3px; }
</style></head>
<body>
<h1>{{ $template->name }}</h1>
<p>{{ optional($template->console)->name }} · version {{ $version->version_number }}</p>
@if(!empty($show))
    <p>{{ $show->name }} · {{ $show->event_type }}</p>
@endif
<p>Setup reference. This document does not transfer settings to the mixer.</p>
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
</body></html>

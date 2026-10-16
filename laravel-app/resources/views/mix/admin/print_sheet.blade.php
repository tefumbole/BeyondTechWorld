<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $template->name }}</title>
<style>
    body { font-family: sans-serif; color: #111; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border-bottom: 1px solid #ccc; text-align: left; padding: 4px; font-size: 12px; }
    @media print { .noprint { display: none; } }
</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Print</button></p>
<h1>{{ $template->name }}</h1>
<p>{{ optional($template->console)->name }} · version {{ $version->version_number }} · {{ $template->channel_count }} channels on this sheet</p>
<p>Setup reference. This page does not transfer settings to the mixer.</p>
<table>
    <thead><tr><th>Ch</th><th>Name</th><th>Instrument</th><th>Preset</th><th>Bus</th><th>Link</th></tr></thead>
    <tbody>
    @foreach($version->channels as $channel)
        <tr>
            <td>{{ $channel->position }}</td>
            <td>{{ $channel->name }}</td>
            <td>{{ optional($channel->instrument)->name }}</td>
            <td>@if($channel->presetVersion){{ optional($channel->presetVersion->preset)->name }} v{{ $channel->presetVersion->version_number }}@endif</td>
            <td>{{ optional($channel->bus)->name }}</td>
            <td>{{ $channel->stereo_link }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body></html>

@extends('mix.public.layout')
@section('title', $row->name)
@section('content')
<h1>{{ $row->name }}</h1>
<p class="muted">Reference chain. This preset does not master an audio file.</p>
<ul>
    @foreach($row->chain() as $step)<li>{{ $step }}</li>@endforeach
</ul>
<p>Target {{ $row->target_lufs !== null ? $row->target_lufs.' LUFS' : '—' }} · true peak {{ $row->true_peak_db !== null ? $row->true_peak_db.' dBTP' : '—' }}</p>
<p>{{ $row->output_format }} {{ $row->sample_rate }} {{ $row->bit_depth ? $row->bit_depth.'-bit' : '' }}</p>
<p>{{ $row->notes }}</p>
@endsection

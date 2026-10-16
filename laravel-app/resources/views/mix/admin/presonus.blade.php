@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>PreSonus preset library</h3>
    @include('mix.admin.nav')
    <div class="card p-3">
        <p>{{ $report['what_it_is'] }}</p>
        <p><strong>Format.</strong> {{ $report['format'] }}</p>
        <p><strong>Parameters.</strong> {{ $report['decoded'] }}</p>
        <p><strong>32SX.</strong> {{ $report['compatibility'] }}</p>
        <p><strong>Export.</strong> {{ $report['export'] }}</p>
        <p><strong>Redistribution.</strong> {{ $report['license'] }}</p>
        <p><a href="{{ $report['source'] }}" target="_blank" rel="noopener">Official PreSonus article</a> · updated {{ $report['updated'] }}</p>
    </div>
    <h5 class="mt-3">Presets in the PreSonus collection</h5>
    @forelse($rows as $row)
        <div>{{ $row->name }} · {{ $row->published ? 'public' : 'private' }} @if($row->official_url)<a href="{{ $row->official_url }}">source</a>@endif</div>
    @empty
        <p>No official preset parameters are hosted here. Add a PreSonus-collection preset only when you have verified the values and the right to store them.</p>
    @endforelse
</div></section>
@endsection

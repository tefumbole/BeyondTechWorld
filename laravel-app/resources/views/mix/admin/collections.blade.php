@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Preset collections</h3>
    @include('mix.admin.nav')
    <div class="row">
        <div class="col-md-6"><h5>Beyond</h5>@forelse($beyond as $row)<div><a href="{{ route('mix.presets.edit', $row->id) }}">{{ $row->name }}</a></div>@empty<p>None yet.</p>@endforelse</div>
        <div class="col-md-6"><h5>PreSonus</h5>@forelse($presonus as $row)<div><a href="{{ route('mix.presets.edit', $row->id) }}">{{ $row->name }}</a></div>@empty<p>None hosted. Use the PreSonus library page for the official link.</p>@endforelse</div>
    </div>
</div></section>
@endsection

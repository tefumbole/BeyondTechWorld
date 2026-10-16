@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Mix & Master</h3>
    @include('mix.admin.nav')
    <p>Reference library for channel presets, complete mix templates, and show sheets. Saving a preset creates a new version. A show keeps the version it was given.</p>
    <div class="row">
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Presets</div><strong>{{ $presets }}</strong><div class="small">{{ $published }} published</div></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Mix templates</div><strong>{{ $templates }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Show templates</div><strong>{{ $shows }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Imports waiting</div><strong>{{ $imports }}</strong></div></div>
    </div>
    <p class="mt-3 text-muted">These pages display and export settings. They do not transfer a scene to the StudioLive.</p>
</div></section>
@endsection

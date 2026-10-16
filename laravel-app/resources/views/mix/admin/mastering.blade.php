@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Mastering presets</h3>
    @include('mix.admin.nav')
    <p>A mastering preset is a reference chain and a loudness target. It does not process audio.</p>
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('mix.mastering.store') }}">@csrf @include('mix.admin.mastering_fields', ['row' => null])<button class="btn btn-primary" type="submit">Save mastering preset</button></form>
    </div>
    @foreach($rows as $row)
        <div class="card p-3 mb-2">
            <form method="POST" action="{{ route('mix.mastering.update', $row->id) }}">@csrf @include('mix.admin.mastering_fields', ['row' => $row])<button class="btn btn-sm btn-primary" type="submit">Update</button></form>
        </div>
    @endforeach
</div></section>
@endsection

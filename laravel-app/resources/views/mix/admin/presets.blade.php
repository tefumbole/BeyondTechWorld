@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Instrument presets</h3>
    @include('mix.admin.nav')
    <form method="GET" class="form-inline mb-3">
        <input class="form-control mr-2" name="q" value="{{ $q }}" placeholder="Kick Drum, Snare Top, vocal">
        <button class="btn btn-primary" type="submit">Search</button>
        <a class="btn btn-outline-primary ml-2" href="{{ route('mix.presets.create') }}">New preset</a>
    </form>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Name</th><th>Instrument</th><th>Source</th><th>Version</th><th>Public</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td>{{ optional($row->instrument)->name }}</td>
                    <td>{{ $row->sourceName() }}</td>
                    <td>{{ optional($row->currentVersion)->version_number }}</td>
                    <td>{{ $row->published ? 'Yes' : 'No' }}</td>
                    <td>
                        <a href="{{ route('mix.presets.edit', $row->id) }}">Edit</a>
                        · <a href="{{ route('mix.presets.compare', $row->id) }}">Compare</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No presets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></section>
@endsection

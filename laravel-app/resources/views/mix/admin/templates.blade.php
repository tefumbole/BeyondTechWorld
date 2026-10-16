@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Complete mix templates</h3>
    @include('mix.admin.nav')
    <p>Each channel stores a preset version. Editing a preset later does not change this template until you upgrade it.</p>
    @forelse($rows as $row)
        <div class="card p-3 mb-2">
            <strong>{{ $row->name }}</strong>
            <span class="text-muted">{{ optional($row->console)->name }} · {{ $row->channel_count }} channels · version {{ optional($row->currentVersion)->version_number }} · {{ $row->published ? 'public' : 'private' }}</span>
            <div class="mt-2">
                <a class="btn btn-sm btn-primary" href="{{ route('mix.templates.edit', $row->id) }}">Edit</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mix.templates.print', $row->id) }}">Print sheet</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mix.templates.pdf', $row->id) }}">PDF</a>
                <form class="d-inline" method="POST" action="{{ route('mix.templates.duplicate', $row->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Duplicate</button></form>
                <form class="d-inline" method="POST" action="{{ route('mix.templates.upgrade', $row->id) }}">@csrf<button class="btn btn-sm btn-outline-warning" type="submit">Upgrade preset versions</button></form>
                <form class="d-inline" method="POST" action="{{ route('mix.templates.publish', $row->id) }}">@csrf<button class="btn btn-sm btn-outline-success" type="submit">{{ $row->published ? 'Unpublish' : 'Publish' }}</button></form>
            </div>
        </div>
    @empty
        <div class="alert alert-info">No templates yet.</div>
    @endforelse
</div></section>
@endsection

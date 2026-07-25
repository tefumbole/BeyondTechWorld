@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h1 class="ct-title">Clause Library</h1>
                <p class="ct-subtitle">Reusable clauses to insert into templates. Editing a clause does not change contracts already created.</p>
            </div>
            <a href="{{ route('contracts.clauses.create') }}" class="ct-btn"><i class="dripicons-plus"></i> New Clause</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="ct-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Code</th><th>Title</th><th>Category</th><th>Active</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($clauses as $c)
                            <tr>
                                <td><code>{{ $c->code }}</code></td>
                                <td>{{ $c->title }}</td>
                                <td>{{ $c->category ?: '—' }}</td>
                                <td>{{ $c->active ? 'Yes' : 'No' }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('contracts.clauses.edit', $c->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                    <form method="POST" action="{{ route('contracts.clauses.destroy', $c->id) }}" class="d-inline" onsubmit="return confirm('Delete this clause?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No clauses yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

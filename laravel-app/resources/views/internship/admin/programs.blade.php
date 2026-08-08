@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Internship Programs</h1>
        <div class="ip-card">
            <table class="table ip-table">
                <thead><tr><th>Code</th><th>Name</th><th>Version</th><th>Status</th><th>Tasks</th><th></th></tr></thead>
                <tbody>
                @forelse($programs as $p)
                    <tr>
                        <td>{{ $p->code }}</td>
                        <td>{{ $p->name }}</td>
                        <td>{{ $p->version }}</td>
                        <td><span class="ip-badge {{ $p->status === 'published' ? 'active' : '' }}">{{ $p->status }}</span></td>
                        <td>{{ $p->tasks_count }}</td>
                        <td><a href="{{ route('internship.programs.show', $p->id) }}">View days</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No programs yet. Import the curriculum JSON.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Internships</a>
        <h1 class="ip-title">Supervisors</h1>
        <p class="ip-meta mb-3">Internship supervisors and people currently assigned to active placements.</p>

        <form method="GET" action="{{ route('internship.supervisors') }}" class="ip-card mb-3">
            <div class="d-flex flex-wrap" style="gap:8px;">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px;"
                       placeholder="Search name, email, phone">
                <button class="ip-btn" type="submit">Search</button>
            </div>
        </form>

        <div class="ip-card">
            <table class="table ip-table mb-0">
                <thead>
                    <tr>
                        <th>Supervisor</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Active placements</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $u)
                    <tr>
                        <td><strong>{{ $u->name }}</strong></td>
                        <td class="ip-meta">{{ $u->phone ?: '—' }}</td>
                        <td class="ip-meta">{{ $u->email ?: '—' }}</td>
                        <td>{{ (int) ($counts[$u->id] ?? 0) }}</td>
                        <td>
                            <a class="ip-btn ip-btn-outline ip-btn-sm" href="{{ route('internship.tasks', ['q' => $u->name]) }}">View tasks</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No supervisors found yet. Assign supervisors when placing interns.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
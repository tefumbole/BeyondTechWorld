@extends('layout.main')
@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap" style="gap:12px;">
            <div>
                <h1 class="jb-title">Applicants</h1>
                <p class="jb-subtitle">Unique people who have applied (separate from individual application records).</p>
            </div>
        </div>

        <div class="jb-card">
            <form method="GET" action="{{ route('jobs.applicants') }}" class="d-flex flex-wrap" style="gap:10px;">
                <input type="search" name="q" value="{{ $q }}" class="jb-field" placeholder="Search name, email, phone…" style="max-width:320px;">
                <button type="submit" class="jb-btn"><i class="dripicons-search"></i> Search</button>
            </form>
        </div>

        <div class="jb-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Country</th>
                            <th>Applications</th>
                            <th>Latest status</th>
                            <th>Last applied</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($people as $i => $person)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ $person['full_name'] }}</strong></td>
                                <td>{{ $person['email'] ?: '—' }}</td>
                                <td>{{ $person['phone'] ?: '—' }}</td>
                                <td>{{ $person['country'] ?: '—' }}</td>
                                <td>{{ $person['applications_count'] }}</td>
                                <td><span class="jb-badge">{{ str_replace('_', ' ', $person['latest_status']) }}</span></td>
                                <td>{{ $person['submitted_at'] ? \Carbon\Carbon::parse($person['submitted_at'])->format('Y-m-d H:i') : '—' }}</td>
                                <td>
                                    <a class="jb-btn-secondary" href="{{ route('jobs.applications.show', $person['latest_application_id']) }}">View latest</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No applicants found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

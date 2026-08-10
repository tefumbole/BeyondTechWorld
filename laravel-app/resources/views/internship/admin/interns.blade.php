@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1200px;">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Internships</a>
        <h1 class="ip-title">Interns</h1>
        <p class="ip-meta mb-3">Accepted or hired internship applicants. <strong>Ready to assign</strong> lists selected interns who still need a supervisor (program alone is not enough). Each placed intern must configure their own Working Week before daily tasks release.</p>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="ip-nav">
            @php $tabCounts = $tabCounts ?? []; @endphp
            @foreach([
                'ready' => 'Ready to assign',
                'selected' => 'Selected',
                'hired' => 'Hired',
                'placed' => 'Already placed',
                'all' => 'All accepted',
            ] as $key => $label)
                <a class="ip-btn {{ ($status ?? '') === $key ? '' : 'ip-btn-outline' }}"
                   href="{{ route('internship.interns', ['status' => $key, 'q' => request('q')]) }}">
                    {{ $label }}
                    <span class="ip-nav-count">{{ (int) ($tabCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('internship.interns') }}" class="ip-card mb-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="d-flex flex-wrap" style="gap:8px;">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px;"
                       placeholder="Search name, email, phone">
                <button class="ip-btn" type="submit">Search</button>
            </div>
        </form>

        <div class="ip-card">
            <div class="table-responsive">
                <table class="table ip-table mb-0">
                    <thead>
                        <tr>
                            <th>Intern</th>
                            <th>Program preference</th>
                            <th>Status</th>
                            <th>Placement</th>
                            <th>Working week</th>
                            <th>Applied</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($interns as $app)
                        @php
                            $enrolment = $enrolmentsByApp[$app->id] ?? null;
                            $student = $enrolment ? $enrolment->student : null;
                            $wwLabel = $student ? \App\Support\InternCompliance::workingWeekLabel($student) : null;
                            $wwConfigured = $student ? \App\Support\InternCompliance::workingWeekConfigured($student) : false;
                            $wwOnApp = ! $wwConfigured && $app->hasWorkingWeekOnApplication();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $app->full_name }}</strong>
                                <div class="ip-meta">{{ $app->email }}</div>
                                <div class="ip-meta">{{ $app->whatsapp_number ?: $app->phone }}</div>
                            </td>
                            <td>
                                {{ optional($app->internshipProgram)->displayName() ?: '—' }}
                                @if($app->internship_duration_days)
                                    <div class="ip-meta">{{ $app->internship_duration_days }} days</div>
                                @endif
                                <div class="ip-meta">{{ optional($app->job)->title }}</div>
                            </td>
                            <td><span class="ip-badge {{ $app->status === 'hired' ? 'active' : 'blue' }}">{{ $app->statusLabel() }}</span></td>
                            <td>
                                @if($enrolment)
                                    <span class="ip-badge active">{{ ucfirst($enrolment->status) }}</span>
                                    <div class="ip-meta">{{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }} days</div>
                                @else
                                    <span class="ip-meta">Not placed</span>
                                @endif
                            </td>
                            <td>
                                @if($wwConfigured)
                                    <span class="ip-badge active">Configured</span>
                                    <div class="ip-meta">{{ $wwLabel ?: 'Saved' }}</div>
                                @elseif($wwOnApp)
                                    <span class="ip-badge blue">Saved on application</span>
                                    <div class="ip-meta">Syncs when account is created</div>
                                @elseif(! $enrolment)
                                    <span class="ip-meta">—</span>
                                @else
                                    <span class="ip-badge warn">Missing</span>
                                    <div class="ip-meta">Tasks will not release</div>
                                @endif
                            </td>
                            <td class="ip-meta">{{ optional($app->submitted_at)->format('d M Y') ?: '—' }}</td>
                            <td class="text-nowrap">
                                <a class="ip-btn ip-btn-sm" href="{{ route('jobs.applicants.placement.edit', $app->id) }}">
                                    {{ $enrolment ? 'Edit placement' : 'Assign' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No interns match this filter.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $interns->links() }}</div>
        </div>
    </div>
</section>
@endsection

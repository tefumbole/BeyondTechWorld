@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php
    $todayTasksByEnrolment = $todayTasksByEnrolment ?? collect();
    $today = $today ?? now()->toDateString();
@endphp
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1400px;">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Internships</a>
        <h1 class="ip-title">Interns</h1>
        <p class="ip-meta mb-3">Accepted or hired internship applicants. <strong>Ready to assign</strong> lists selected interns who still need a supervisor (program alone is not enough). Working weeks saved on application sync to the ERP when the intern is placed. Daily tasks also go out on WhatsApp with a Word handbook (student + supervisor).</p>

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
                {{-- Tab switch clears search so badges (full totals) match the unfiltered list --}}
                <a class="ip-btn {{ ($status ?? '') === $key ? '' : 'ip-btn-outline' }}"
                   href="{{ route('internship.interns', ['status' => $key]) }}">
                    {{ $label }}
                    <span class="ip-nav-count">{{ (int) ($tabCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks', ['status' => 'today']) }}">Today’s tasks</a>
        </div>

        <form method="GET" action="{{ route('internship.interns') }}" class="ip-card mb-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px;"
                       placeholder="Search name, email, phone">
                <button class="ip-btn" type="submit">Search</button>
                @if(request()->filled('q'))
                    <a class="ip-btn ip-btn-outline" href="{{ route('internship.interns', ['status' => $status]) }}">Clear search</a>
                    <span class="ip-meta mb-0">Filtered to “{{ request('q') }}”. Clear search or switch tabs to see all {{ (int) ($tabCounts[$status] ?? 0) }}.</span>
                @endif
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
                            <th>Current task</th>
                            <th>Applied</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($interns as $app)
                        @php
                            $enrolment = $enrolmentsByApp[$app->id] ?? null;
                            $student = $enrolment ? $enrolment->student : null;
                            $wwConfigured = $student ? \App\Support\InternCompliance::workingWeekConfigured($student) : false;
                            $wwOnApp = ! $wwConfigured && $app->hasWorkingWeekOnApplication();
                            $wwDetail = $wwConfigured
                                ? \App\Support\InternCompliance::workingWeekDetailLabel($student)
                                : ($app->hasWorkingWeekOnApplication()
                                    ? \App\Support\InternCompliance::workingWeekDetailLabel(null, $app->workingWeekData())
                                    : null);
                            $todayAssignment = $enrolment ? ($todayTasksByEnrolment[$enrolment->id] ?? null) : null;
                            $isWorkingToday = $student
                                ? app(\App\Services\Internship\InternshipProgramService::class)->isWorkingDate($student, \Carbon\Carbon::parse($today))
                                : false;
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
                                    @if($enrolment->supervisor)
                                        <div class="ip-meta">Sup: {{ $enrolment->supervisor->name }}</div>
                                    @endif
                                @else
                                    <span class="ip-meta">Not placed</span>
                                @endif
                            </td>
                            <td>
                                @if($wwConfigured)
                                    <span class="ip-badge active">Configured</span>
                                    <div class="ip-meta">{{ $wwDetail ?: 'Saved' }}</div>
                                @elseif($wwOnApp)
                                    <span class="ip-badge blue">Saved on application</span>
                                    <div class="ip-meta">{{ $wwDetail ?: 'Pending sync' }}</div>
                                @elseif(! $enrolment)
                                    <span class="ip-meta">—</span>
                                @else
                                    <span class="ip-badge warn">Missing</span>
                                    <div class="ip-meta">Tasks will not release</div>
                                @endif
                            </td>
                            <td>
                                @if(! $enrolment)
                                    <span class="ip-meta">—</span>
                                @elseif($todayAssignment)
                                    <strong>#{{ $todayAssignment->progression_day }}</strong>
                                    {{ optional($todayAssignment->task)->title ?: 'Day task' }}
                                    <div class="ip-meta">{{ ucfirst(str_replace('_', ' ', $todayAssignment->status)) }}
                                        · {{ optional($todayAssignment->scheduled_work_date)->format('d M') ?: $today }}
                                    </div>
                                    @if($todayAssignment->whatsapp_sent_at)
                                        <div class="ip-meta">WA {{ $todayAssignment->whatsapp_sent_at->format('d M H:i') }}</div>
                                    @endif
                                    <div class="mt-1 d-flex flex-wrap" style="gap:6px;">
                                        <a class="ip-btn ip-btn-sm ip-btn-outline"
                                           href="{{ route('internship.tasks', ['status' => 'open', 'assignment' => $todayAssignment->id]) }}">View</a>
                                        @if(in_array($todayAssignment->status, ['available', 'in_progress', 'revision_required', 'submitted'], true))
                                            <form method="POST" action="{{ route('internship.tasks.resend', $todayAssignment->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Send task WhatsApp + Word handbook to student and supervisor? This may take ~20 seconds.');">
                                                @csrf
                                                <input type="hidden" name="include_supervisors" value="1">
                                                <button type="submit" class="ip-btn ip-btn-sm">Send WA</button>
                                            </form>
                                        @endif
                                    </div>
                                @elseif(! $wwConfigured && ! $wwOnApp)
                                    <span class="ip-badge warn">No WW</span>
                                    <div class="ip-meta">Configure working week</div>
                                @elseif(! $isWorkingToday)
                                    <span class="ip-meta">Not a working day</span>
                                    <div><a class="ip-btn ip-btn-sm ip-btn-outline" href="{{ route('internship.tasks', ['status' => 'today']) }}">Task board</a></div>
                                @else
                                    <span class="ip-meta">Not released yet</span>
                                    <div><a class="ip-btn ip-btn-sm ip-btn-outline" href="{{ route('internship.tasks', ['status' => 'today']) }}">Task board</a></div>
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
                            <td colspan="8" class="text-center text-muted py-4">No interns match this filter.</td>
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

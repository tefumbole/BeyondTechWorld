@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <h1 class="ip-title"><i class="dripicons-graduation"></i> My Internship Placement</h1>
        <p class="ip-meta mb-3">Complete today’s task, then fill your timesheet at the end of each working day. Tasks release only on days in your Working Week.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        <div class="alert alert-info">
            <strong>Daily routine:</strong> Open task → submit work → <a href="{{ route('timesheet.fill', ['date' => date('Y-m-d'), 'intern' => 1]) }}">fill timesheet</a>.
            Update your schedule anytime under <a href="{{ route('timesheet.working-week') }}">Working Week</a>.
        </div>

        <div class="ip-nav">
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.student.portfolio') }}"><i class="dripicons-folder"></i> Portfolio</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('timesheet.fill', ['date' => date('Y-m-d'), 'intern' => 1]) }}"><i class="dripicons-clock"></i> Fill timesheet</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('timesheet.working-week') }}"><i class="dripicons-calendar"></i> Working Week</a>
        </div>

        @if(!$enrolment)
            <div class="ip-card">
                <h5 style="color:#0b3f90;font-weight:700;">No active placement</h5>
                <p class="mb-0 text-muted">You are not enrolled in an internship program yet. Ask an Internship Administrator to enrol you after your application is accepted.</p>
            </div>
        @else
            <div class="ip-card">
                <div class="d-flex justify-content-between flex-wrap" style="gap:8px;">
                    <div>
                        <div class="ip-meta">Program</div>
                        <strong>{{ optional($enrolment->program)->displayName() ?? '—' }}</strong>
                        <div class="ip-meta mt-1">Curriculum days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }} ({{ $enrolment->plannedDurationDays() }} days)</div>
                        <div class="ip-meta mt-1">Supervisor: {{ optional($enrolment->supervisor)->name ?: 'Not assigned' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="ip-meta">Progress</div>
                        <strong style="font-size:1.4rem;color:#0b3f90;">{{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }}</strong>
                        <div><span class="ip-badge {{ $enrolment->status === 'active' ? 'active' : 'warn' }}">{{ ucfirst($enrolment->status) }}</span></div>
                    </div>
                </div>
            </div>

            @if($assignment)
                @php $dayProgress = $assignment->stepProgress(); @endphp
                <div class="ip-card ip-pending">
                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
                        <div style="flex:1;min-width:220px;">
                            <span class="ip-badge blue">Pending task for today</span>
                            <h3 class="mt-2 mb-1" style="color:#0b3f90;font-weight:800;">
                                Task #{{ $assignment->progression_day }} — {{ $assignment->task->title ?? 'Task' }}
                            </h3>
                            <p class="ip-meta mb-2">Status: <strong>{{ str_replace('_', ' ', $assignment->status) }}</strong>
                                · Scheduled: {{ $assignment->scheduled_work_date }}</p>
                            <p class="mb-2">{{ \Illuminate\Support\Str::limit($assignment->task->objective ?? '', 180) }}</p>
                            <div class="ip-progress-wrap" style="max-width:320px;">
                                <div class="ip-progress-bar"><span style="width:{{ $dayProgress['percent'] }}%;"></span></div>
                                <div class="ip-progress-label">Checklist {{ $dayProgress['done'] }}/{{ $dayProgress['total'] }} ({{ $dayProgress['percent'] }}%)</div>
                            </div>
                        </div>
                        <a class="ip-btn" href="{{ route('internship.student.task', $assignment->id) }}">
                            <i class="dripicons-checkmark"></i>
                            {{ in_array($assignment->status, ['submitted'], true) ? 'View submission' : 'Open task' }}
                        </a>
                    </div>
                </div>
            @else
                <div class="ip-card">
                    <h5 style="color:#0b3f90;font-weight:700;">No pending task right now</h5>
                    @if(!$isWorkingToday)
                        <p class="mb-0 text-muted">Today is not one of your configured working days. Set your week under <strong>TimeSheets → Working Week</strong>.</p>
                    @elseif($enrolment->status === 'completed')
                        <p class="mb-0 text-muted">Congratulations — your program is complete. View your portfolio.</p>
                    @else
                        <p class="mb-0 text-muted">Either you are waiting for supervisor review, or the next task will release on your next working day after a Pass.</p>
                    @endif
                </div>
            @endif
        @endif
    </div>
</section>
@endsection

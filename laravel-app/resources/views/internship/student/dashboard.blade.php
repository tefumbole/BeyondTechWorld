@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px;">
            <div>
                <h1 class="ip-title"><i class="dripicons-graduation"></i> My Internship</h1>
                <p class="ip-meta mb-3">See your current task, your supervisors, and how that task was graded. If today’s task has not arrived, request it here. After you finish, upload your work from the task page.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ip-btn ip-btn-outline ip-btn-danger">
                    <i class="dripicons-power"></i> Logout
                </button>
            </form>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @include('internship.student.partials.timesheet-reminder')
        <div class="alert alert-info">
            <strong>How it works:</strong>
            Open your task → follow the checklist → <strong>upload your evidence</strong> →
            <a href="{{ route('timesheet.fill', ['date' => \App\Support\InternCompliance::timesheetFillDate(Auth::user()) ?: date('Y-m-d'), 'intern' => 1]) }}">fill your timesheet</a>
            (working days only).
            Your supervisor reviews the upload. The next task is released as soon as they accept it.
            Schedule: <a href="{{ route('timesheet.working-week') }}">Working Week</a>.
        </div>

        @include('internship.student.partials.student-nav', ['activeNav' => 'task'])

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
                    </div>
                    <div class="text-right">
                        <div class="ip-meta">Progress</div>
                        <strong style="font-size:1.4rem;color:#0b3f90;">{{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }}</strong>
                        <div><span class="ip-badge {{ $enrolment->status === 'active' ? 'active' : 'warn' }}">{{ ucfirst($enrolment->status) }}</span></div>
                    </div>
                </div>
            </div>

            @include('internship.student.partials.supervisors', ['supervisors' => $supervisors ?? []])

            @if(!empty($awaiting) && $awaiting->count())
                <div class="ip-card ip-pending">
                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
                        <div>
                            <span class="ip-badge warn">Awaiting grading</span>
                            <h3 class="mt-2 mb-1" style="color:#c2410c;font-weight:800;">{{ $awaiting->count() }} {{ $awaiting->count() === 1 ? 'submission' : 'submissions' }} with your supervisor</h3>
                            <p class="ip-meta mb-0">These uploads are waiting for review. The next task arrives after they accept.</p>
                        </div>
                        <a class="ip-btn ip-btn-outline" href="{{ route('internship.student.upload') }}">View uploads</a>
                    </div>
                    @foreach($awaiting as $row)
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #fde68a;">
                            <strong>Task #{{ $row->progression_day }} — {{ optional($row->task)->title ?: 'Task' }}</strong>
                            <a href="{{ route('internship.student.task', $row->id) }}">View submission</a>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($assignment)
                @php $dayProgress = $assignment->stepProgress(); @endphp
                <div class="ip-card ip-pending">
                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
                        <div style="flex:1;min-width:220px;">
                            <span class="ip-badge blue">Your current task</span>
                            <h3 class="mt-2 mb-1" style="color:#0b3f90;font-weight:800;">
                                Task #{{ $assignment->progression_day }} — {{ $assignment->task->title ?? 'Task' }}
                            </h3>
                            <p class="ip-meta mb-2">
                                Task status: <strong>{{ str_replace('_', ' ', $assignment->status) }}</strong>
                                · Scheduled: {{ optional($assignment->scheduled_work_date)->format('D d M Y') ?: $assignment->scheduled_work_date }}
                            </p>
                            <p class="mb-2">{{ \Illuminate\Support\Str::limit($assignment->task->objective ?? '', 220) }}</p>
                            <div class="ip-progress-wrap" style="max-width:320px;">
                                <div class="ip-progress-bar"><span style="width:{{ $dayProgress['percent'] }}%;"></span></div>
                                <div class="ip-progress-label">Checklist {{ $dayProgress['done'] }}/{{ $dayProgress['total'] }} ({{ $dayProgress['percent'] }}%)</div>
                            </div>
                            <p class="mb-0 mt-2">
                                <a href="{{ route('timesheet.fill', ['date' => \Carbon\Carbon::parse($assignment->scheduled_work_date)->toDateString(), 'intern' => 1, 'assignment' => $assignment->id]) }}">
                                    Log hours for this task
                                </a>
                            </p>
                        </div>
                        <a class="ip-btn" href="{{ route('internship.student.task', $assignment->id) }}">
                            <i class="dripicons-checkmark"></i>
                            @if($assignment->status === 'submitted')
                                View submission
                            @elseif($assignment->status === 'revision_required')
                                Fix and re-upload
                            @else
                                Open task &amp; upload
                            @endif
                        </a>
                    </div>
                    <div class="mt-3">
                        @include('internship.student.partials.grade-status', ['gradeSummary' => $gradeSummary])
                    </div>
                </div>
            @else
                <div class="ip-card">
                    <h5 style="color:#0b3f90;font-weight:700;">No task in your inbox</h5>
                    @if(!empty($requestState['can_request']))
                        <p>You have not received
                            @if(!empty($requestState['next_day']))
                                Task #{{ $requestState['next_day'] }}
                                @if(!empty($requestState['task_title']))
                                    — {{ $requestState['task_title'] }}
                                @endif
                            @else
                                today’s task
                            @endif
                            yet. Request it now — this only releases the next task in your program, not a different one.
                        </p>
                        <form method="POST" action="{{ route('internship.student.request') }}">
                            @csrf
                            <button class="ip-btn" type="submit"><i class="dripicons-download"></i> Request my task</button>
                        </form>
                    @else
                        @if($enrolment->status === 'completed')
                            <p class="mb-0 text-muted">Congratulations — your program is complete. View your portfolio.</p>
                        @elseif($enrolment->releaseHeldUntil())
                            <p class="mb-0 text-muted">Your supervisor accepted your last task. The next one should appear shortly — refresh this page if it is not here yet.</p>
                        @elseif(!$isWorkingToday)
                            <p class="mb-0 text-muted">Today is not one of your configured working days. Set your week under <strong>TimeSheets → Working Week</strong>.</p>
                        @else
                            <p class="mb-0 text-muted">{{ $requestState['message'] ?? 'Your next task is released as soon as your supervisor accepts your last submission.' }}</p>
                        @endif
                        @if(($requestState['reason'] ?? '') === 'no_week')
                            <p class="mt-2 mb-0"><a href="{{ route('timesheet.working-week') }}">Set your Working Week</a> so tasks can be released.</p>
                        @endif
                    @endif
                </div>

                @if($lastPassed)
                    <div class="ip-card">
                        <div class="ip-meta">Last accepted task</div>
                        <strong>Task #{{ $lastPassed->progression_day }} — {{ optional($lastPassed->task)->title }}</strong>
                        <div class="mt-2">
                            @include('internship.student.partials.grade-status', ['gradeSummary' => $gradeSummary])
                        </div>
                        <a class="ip-btn ip-btn-outline ip-btn-sm mt-2" href="{{ route('internship.student.task', $lastPassed->id) }}">View that task</a>
                    </div>
                @endif
            @endif
        @endif
    </div>
</section>
@endsection

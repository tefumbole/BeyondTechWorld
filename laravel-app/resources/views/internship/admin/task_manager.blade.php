@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php
    $isUpcoming = in_array($status ?? '', ['tomorrow', 'day_after'], true);
    $upcoming = $upcoming ?? collect();
    $missingWw = $missingWw ?? false;
    $missingWeekRows = $missingWeekRows ?? collect();
    $wwLabels = $wwLabels ?? [];
@endphp
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1200px;">
        @include('internship.partials.supervisor_nav', ['ipNavHere' => 'tasks'])
        <div class="mb-3"></div>
        <h1 class="ip-title">Internship Task Manager</h1>
        <p class="ip-meta mb-3">Per-student day tasks: open work, upcoming releases on each student’s working days, and tasks already released. Schedules are student-owned (Working Week), not program-wide. A student only appears as upcoming once their previous submission has been accepted.</p>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if(request()->filled('assignment'))
            <div class="alert alert-info">Showing the selected intern day task (assignment #{{ (int) request('assignment') }}).</div>
        @endif

        <div class="ip-nav">
            @foreach([
                'open' => 'Open tasks',
                'today' => 'Today',
                'tomorrow' => 'Tomorrow',
                'day_after' => 'Day after',
                'assigned' => 'Assigned (released)',
                'available' => 'Available',
                'in_progress' => 'In progress',
                'submitted' => 'Submitted',
                'all' => 'All',
            ] as $key => $label)
                <a class="ip-btn {{ (!$missingWw && ($status ?? '') === $key) ? '' : 'ip-btn-outline' }}"
                   href="{{ route('internship.tasks', ['status' => $key, 'q' => request('q')]) }}">{{ $label }}</a>
            @endforeach
            <a class="ip-btn {{ $missingWw ? '' : 'ip-btn-outline' }}"
               href="{{ route('internship.tasks', ['missing_ww' => 1, 'q' => request('q')]) }}">Missing working week</a>
        </div>

        <form method="GET" action="{{ route('internship.tasks') }}" class="ip-card mb-3">
            @if($missingWw)
                <input type="hidden" name="missing_ww" value="1">
            @else
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="d-flex flex-wrap" style="gap:8px;">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px;"
                       placeholder="Search student name or email">
                <button class="ip-btn" type="submit">Search</button>
            </div>
        </form>

        @if($missingWw)
            <div class="alert alert-warning">
                These active interns have <strong>not configured their Working Week</strong>, so daily day tasks will not release until they save one.
            </div>
            <div class="ip-card">
                <div class="table-responsive">
                    <table class="table ip-table mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Program</th>
                                <th>Working week</th>
                                <th>Supervisor</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($missingWeekRows as $e)
                            <tr>
                                <td>
                                    <strong>{{ optional($e->student)->name ?: '—' }}</strong>
                                    <div class="ip-meta">{{ optional($e->student)->email }}</div>
                                </td>
                                <td>{{ optional($e->program)->displayName() ?: '—' }}</td>
                                <td><span class="ip-badge warn">Missing</span></td>
                                <td class="ip-meta">{{ optional($e->supervisor)->name ?: '—' }}</td>
                                <td class="text-nowrap">
                                    <a class="ip-btn ip-btn-sm ip-btn-outline" href="{{ url('/admin/timesheet/working-week') }}" target="_blank" rel="noopener">Open link</a>
                                    <form method="POST" action="{{ route('internship.interns.request_week', $e->id) }}" class="d-inline"
                                          onsubmit="return confirm('Send this intern a WhatsApp with the Working Week link?');">
                                        @csrf
                                        <button type="submit" class="ip-btn ip-btn-sm">Request week</button>
                                    </form>
                                    <a class="ip-btn ip-btn-sm ip-btn-outline" href="{{ route('internship.enrol.edit', $e->id) }}">Edit placement</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">All active interns have a Working Week configured.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif($isUpcoming)
            <div class="alert alert-info">
                Day tasks for <strong>{{ \Carbon\Carbon::parse($targetDate)->format('l, d M Y') }}</strong> — one row per student.
                <em>Scheduled</em> = predicted release (not sent yet).
                <em>Released</em> = already assigned. After a supervisor accepts a submission, the next task is released immediately; that task’s timesheet is due on the intern’s next working day.
            </div>
            <div class="ip-card">
                <div class="table-responsive">
                    <table class="table ip-table mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Program / Task</th>
                                <th>Date</th>
                                <th>Working week</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Supervisor</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($upcoming as $row)
                            @php
                                $assignment = $row['assignment'] ?? null;
                                $student = $row['student'] ?? null;
                                $program = $row['program'] ?? null;
                                $task = $row['task'] ?? null;
                                $source = $row['source'] ?? 'scheduled';
                                $sid = optional($student)->id;
                                $wwLabel = $sid ? ($wwLabels[$sid] ?? null) : null;
                            @endphp
                            <tr>
                                <td><strong>{{ optional($student)->name ?: '—' }}</strong>
                                    <div class="ip-meta">{{ optional($student)->email }}</div>
                                </td>
                                <td>
                                    <div class="ip-meta">{{ $program ? $program->displayName() : '—' }}</div>
                                    <strong>#{{ $row['progression_day'] ?? '—' }} — {{ optional($task)->title ?: 'Task' }}</strong>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($row['scheduled_work_date'])->format('d M Y') }}</td>
                                <td>
                                    @if($wwLabel)
                                        <span class="ip-badge active">{{ $wwLabel }}</span>
                                    @else
                                        <span class="ip-badge warn">Missing</span>
                                    @endif
                                </td>
                                <td>
                                    @if($source === 'assigned')
                                        <span class="ip-badge active">Released</span>
                                    @else
                                        <span class="ip-badge warn">Scheduled</span>
                                    @endif
                                </td>
                                <td>
                                    @if($assignment)
                                        <span class="ip-badge">{{ str_replace('_', ' ', $assignment->status) }}</span>
                                    @else
                                        <span class="ip-meta">Not released yet</span>
                                    @endif
                                </td>
                                <td class="ip-meta">{{ optional($row['supervisor'] ?? null)->name ?: '—' }}</td>
                                <td class="text-nowrap">
                                    @if($assignment)
                                        <form method="POST" action="{{ route('internship.tasks.resend', $assignment->id) }}" class="d-inline"
                                              onsubmit="return confirm('Resend this daily task WhatsApp (Word handbook) to {{ addslashes(optional($student)->name ?: 'the student') }} and supervisor(s)?');">
                                            @csrf
                                            <input type="hidden" name="include_supervisors" value="1">
                                            <button type="submit" class="ip-btn ip-btn-sm">
                                                <i class="dripicons-clockwise"></i> Resend
                                            </button>
                                        </form>
                                    @else
                                        <span class="ip-meta">Auto-releases at the working-day start time</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No student day tasks for this date (check Working Weeks if none appear).</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            @if(($status ?? '') === 'assigned')
                <div class="alert alert-info mb-3">
                    <strong>Assigned</strong> lists day tasks that were released to students (<code>released_at</code> set) — not the full program curriculum.
                </div>
            @endif
            <div class="ip-card">
                <div class="table-responsive">
                    <table class="table ip-table mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Program / Task</th>
                                <th>Date</th>
                                <th>Working week</th>
                                <th>Status</th>
                                <th style="min-width:160px;">Checklist</th>
                                <th>WhatsApp</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse(($assignments ?? collect()) as $a)
                            @php
                                $progress = $a->stepProgress();
                                $student = optional($a->enrolment)->student;
                                $program = optional($a->enrolment)->program;
                                $sid = optional($student)->id;
                                $wwLabel = $sid ? ($wwLabels[$sid] ?? null) : null;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ optional($student)->name ?: '—' }}</strong>
                                    <div class="ip-meta">{{ optional($a->enrolment->supervisor)->name ? 'Sup: '.$a->enrolment->supervisor->name : '' }}</div>
                                </td>
                                <td>
                                    <div class="ip-meta">{{ $program ? $program->displayName() : '—' }}</div>
                                    <strong>#{{ $a->progression_day }} — {{ optional($a->task)->title ?: 'Task' }}</strong>
                                </td>
                                <td>{{ optional($a->scheduled_work_date)->format('d M Y') ?: '—' }}</td>
                                <td>
                                    @if($wwLabel)
                                        <span class="ip-badge active">{{ $wwLabel }}</span>
                                    @else
                                        <span class="ip-badge warn">Missing</span>
                                    @endif
                                </td>
                                <td><span class="ip-badge">{{ str_replace('_', ' ', $a->status) }}</span></td>
                                <td>
                                    <div class="ip-progress-wrap">
                                        <div class="ip-progress-bar"><span style="width:{{ $progress['percent'] }}%;"></span></div>
                                        <div class="ip-progress-label">{{ $progress['done'] }}/{{ $progress['total'] }} steps ({{ $progress['percent'] }}%)</div>
                                    </div>
                                </td>
                                <td class="ip-meta">
                                    @if($a->whatsapp_sent_at)
                                        Sent {{ optional($a->whatsapp_sent_at)->format('d M H:i') }}
                                    @else
                                        Not sent
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <form method="POST" action="{{ route('internship.tasks.resend', $a->id) }}" class="d-inline"
                                          onsubmit="return confirm('Resend this daily task WhatsApp (Word handbook) to {{ addslashes(optional($student)->name ?: 'the student') }} and supervisor(s)?');">
                                        @csrf
                                        <input type="hidden" name="include_supervisors" value="1">
                                        <button type="submit" class="ip-btn ip-btn-sm" title="Resend WhatsApp">
                                            <i class="dripicons-clockwise"></i> Resend
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No tasks match this filter.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($assignments)
                    <div class="mt-3">{{ $assignments->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</section>
@endsection

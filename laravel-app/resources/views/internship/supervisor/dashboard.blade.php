@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
            <div>
                <h1 class="ip-title mb-0">Supervisor</h1>
                <p class="ip-meta mb-0">
                    @if(\App\Support\InternCompliance::isInternshipAdmin(auth()->user()))
                        All internship placements, open tasks, and submissions waiting to be graded.
                    @else
                        Your interns and the tasks assigned to them.
                    @endif
                </p>
            </div>
            @include('internship.partials.supervisor_nav', ['ipNavHere' => 'home', 'pendingGrades' => $stats['pending_grades'] ?? 0])
        </div>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <div class="ip-card p-3">
                    <div class="ip-meta">My interns</div>
                    <div class="ip-title" style="font-size:1.5rem;">{{ $stats['interns'] }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="ip-card p-3">
                    <div class="ip-meta">Active</div>
                    <div class="ip-title" style="font-size:1.5rem;">{{ $stats['active'] }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="ip-card p-3">
                    <div class="ip-meta">Open tasks</div>
                    <div class="ip-title" style="font-size:1.5rem;">{{ $stats['open_tasks'] }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="ip-card p-3">
                    <div class="ip-meta">To grade</div>
                    <div class="ip-title" style="font-size:1.5rem;">{{ $stats['pending_grades'] }}</div>
                </div>
            </div>
        </div>

        <div class="ip-card mb-4">
            <h2 class="h5 mb-3">My interns</h2>
            <table class="table ip-table">
                <thead>
                <tr>
                    <th>Intern</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Current task</th>
                    <th>Working week</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($internRows as $row)
                    @php
                        $e = $row['enrolment'];
                        $open = $row['open_assignment'];
                        $student = $e->student;
                        $ww = $student ? \App\Support\InternCompliance::workingWeekInspect($student) : ['label' => null, 'configured' => false];
                    @endphp
                    <tr>
                        <td>{{ optional($student)->name ?: '—' }}</td>
                        <td>{{ optional($e->program)->displayName() }}</td>
                        <td><span class="ip-badge">{{ $e->status }}</span></td>
                        <td>{{ $e->completed_count }}/{{ $e->plannedDurationDays() }}</td>
                        <td>
                            @if($open)
                                #{{ $open->progression_day }} — {{ optional($open->task)->title ?: 'Task' }}
                                <span class="ip-badge">{{ $open->status }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($ww['configured'])
                                <span class="ip-badge active">Set</span>
                                <div class="ip-meta">{{ $ww['label'] }}</div>
                            @else
                                <span class="ip-badge warn">Missing</span>
                            @endif
                            <div class="mt-1">
                                <a class="ip-btn ip-btn-sm ip-btn-outline" href="{{ route('internship.supervisor.working_week', $e->id) }}">View week</a>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            @if($open)
                                <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks', ['assignment' => $open->id]) }}">View task</a>
                            @endif
                            <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.place', $e->id) }}">Place</a>
                            @if(\App\Support\InternCompliance::isInternshipAdmin(auth()->user()))
                                <form method="POST" action="{{ route('internship.supervisor.destroy', $e->id) }}" class="d-inline"
                                      onsubmit="return confirm('Permanently delete {{ addslashes(optional($student)->name ?: 'this intern') }} from the system? Their login, placement, submissions and timesheets will be removed. This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ip-btn ip-btn-outline ip-btn-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No interns assigned to you yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="ip-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Open tasks</h2>
                        <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks') }}">All tasks</a>
                    </div>
                    <table class="table ip-table">
                        <thead><tr><th>Intern</th><th>Task</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($openTasks as $a)
                            <tr>
                                <td>{{ optional(optional($a->enrolment)->student)->name ?: '—' }}</td>
                                <td>
                                    <a href="{{ route('internship.tasks', ['assignment' => $a->id]) }}">
                                        #{{ $a->progression_day }} — {{ optional($a->task)->title }}
                                    </a>
                                </td>
                                <td><span class="ip-badge">{{ $a->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No open tasks.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="ip-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Waiting for grade</h2>
                        <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}">Queue</a>
                    </div>
                    <table class="table ip-table">
                        <thead><tr><th>Intern</th><th>Task</th><th></th></tr></thead>
                        <tbody>
                        @forelse($recentSubmissions as $s)
                            <tr>
                                <td>{{ optional($s->student)->name }}</td>
                                <td>#{{ optional($s->assignment)->progression_day }} — {{ optional(optional($s->assignment)->task)->title }}</td>
                                <td><a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.show', $s->id) }}">Grade</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Nothing waiting to grade.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

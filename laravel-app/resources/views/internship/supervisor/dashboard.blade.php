@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
            <div>
                <h1 class="ip-title mb-0">Supervisor</h1>
                <p class="ip-meta mb-0">Your interns and the tasks assigned to them.</p>
            </div>
            <div class="d-flex flex-wrap" style="gap:8px;">
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.students') }}">My Interns</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks') }}">Task Manager</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}">Grade Queue
                    @if(($stats['pending_grades'] ?? 0) > 0)
                        <span class="beyond-attention-badge">{{ $stats['pending_grades'] }}</span>
                    @endif
                </a>
            </div>
        </div>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif

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
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($internRows as $row)
                    @php $e = $row['enrolment']; $open = $row['open_assignment']; @endphp
                    <tr>
                        <td>{{ optional($e->student)->name ?: '—' }}</td>
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
                        <td class="text-nowrap">
                            @if($open)
                                <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks', ['assignment' => $open->id]) }}">View task</a>
                            @endif
                            <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.place', $e->id) }}">Place</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No interns assigned to you yet.</td></tr>
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

@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
            <h1 class="ip-title mb-0">Grade Queue</h1>
            <div class="d-flex flex-wrap" style="gap:8px;">
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.dashboard') }}">Supervisor Home</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.students') }}">My Interns</a>
            </div>
        </div>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <p class="ip-meta">A student receives their next task only after you accept the current submission. It is then delivered on their next working day.
            @if(($slaDays ?? 0) > 0)
                Anything left unreviewed for {{ $slaDays }} working day{{ $slaDays == 1 ? '' : 's' }} is accepted automatically so the placement is not delayed.
            @endif
        </p>
        <div class="ip-card">
            <table class="table ip-table">
                <thead><tr><th>Student</th><th>Program</th><th>Task</th><th>Submitted</th><th>Review due</th><th></th></tr></thead>
                <tbody>
                @forelse($submissions as $s)
                    @php $row = $sla[$s->id] ?? null; @endphp
                    <tr>
                        <td>{{ optional($s->student)->name }}</td>
                        <td>{{ optional(optional(optional($s->assignment)->enrolment)->program)->name ?: '—' }}</td>
                        <td>#{{ optional($s->assignment)->progression_day }} — {{ optional(optional($s->assignment)->task)->title }}</td>
                        <td>{{ optional($s->submitted_at)->format('d M Y H:i') }}
                            @if($row)
                                <div class="ip-meta">waiting {{ $row['waiting_hours'] }}h</div>
                            @endif
                        </td>
                        <td>
                            @if($row && $row['deadline'])
                                <span class="ip-badge {{ $row['overdue'] ? 'warn' : 'active' }}">
                                    {{ $row['deadline']->format('D d M H:i') }}
                                </span>
                                @if($row['overdue'])
                                    <div class="ip-meta">auto-accepts on next run</div>
                                @endif
                            @else
                                <span class="ip-meta">No deadline</span>
                            @endif
                        </td>
                        <td><a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.show', $s->id) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No submissions waiting.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $submissions->links() }}
        </div>
    </div>
</section>
@endsection

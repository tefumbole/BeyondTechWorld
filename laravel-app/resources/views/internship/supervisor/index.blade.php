@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="ip-page-head">
            <div>
                <h1 class="ip-title mb-0">Grade Queue</h1>
                <p class="ip-meta mb-0">{{ (int) ($pendingGrades ?? $submissions->total()) }} waiting to be graded</p>
            </div>
            @include('internship.partials.supervisor_nav', ['ipNavHere' => 'queue', 'pendingGrades' => $pendingGrades ?? $submissions->total()])
        </div>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <p class="ip-meta">Every ungraded submission is listed here. A student receives their next task only after you accept the current work.
            @if(($slaDays ?? 0) > 0)
                Anything left unreviewed for {{ $slaDays }} working day{{ $slaDays == 1 ? '' : 's' }} is accepted automatically so the placement is not delayed.
            @endif
        </p>
        <div class="ip-queue-cards">
            @forelse($submissions as $s)
                @php $row = $sla[$s->id] ?? null; @endphp
                <article class="ip-queue-card">
                    <div>
                        <strong>{{ optional($s->student)->name ?: 'Intern' }}</strong>
                        <div class="ip-meta">{{ optional(optional(optional($s->assignment)->enrolment)->program)->name ?: '—' }}</div>
                        <div class="mt-1">#{{ optional($s->assignment)->progression_day }} — {{ optional(optional($s->assignment)->task)->title }}</div>
                        <div class="ip-meta mt-1">
                            Submitted {{ optional($s->submitted_at)->format('d M Y H:i') ?: '—' }}
                            @if($row) · waiting {{ $row['waiting_hours'] }}h @endif
                        </div>
                        @if($row && $row['deadline'])
                            <span class="ip-badge {{ $row['overdue'] ? 'warn' : 'active' }}">
                                Due {{ $row['deadline']->format('D d M H:i') }}
                            </span>
                        @endif
                    </div>
                    <a class="ip-btn" href="{{ route('internship.supervisor.show', $s->id) }}">Review</a>
                </article>
            @empty
                <div class="ip-card mb-0"><p class="mb-0">No submissions waiting.</p></div>
            @endforelse
        </div>
        <div class="ip-card ip-queue-table">
            <div class="ip-table-wrap">
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
            </div>
        </div>
        <div class="ip-pager">{{ $submissions->links() }}</div>
    </div>
</section>
@endsection

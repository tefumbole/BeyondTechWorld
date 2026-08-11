@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
            <h1 class="ip-title mb-0">Submissions to grade</h1>
            <div class="d-flex flex-wrap" style="gap:8px;">
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.dashboard') }}">Supervisor Home</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.students') }}">My Interns</a>
            </div>
        </div>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="ip-card">
            <table class="table ip-table">
                <thead><tr><th>Student</th><th>Program</th><th>Task</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                @forelse($submissions as $s)
                    <tr>
                        <td>{{ optional($s->student)->name }}</td>
                        <td>{{ optional(optional(optional($s->assignment)->enrolment)->program)->name ?: '—' }}</td>
                        <td>#{{ optional($s->assignment)->progression_day }} — {{ optional(optional($s->assignment)->task)->title }}</td>
                        <td>{{ optional($s->submitted_at)->format('d M Y H:i') }}</td>
                        <td><a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.show', $s->id) }}">Grade</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No submissions waiting.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $submissions->links() }}
        </div>
    </div>
</section>
@endsection

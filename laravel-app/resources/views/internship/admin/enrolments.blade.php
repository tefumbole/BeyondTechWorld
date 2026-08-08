@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="ip-title mb-0">Enrolments</h1>
            <a class="ip-btn" href="{{ route('internship.enrol.create') }}">Enrol student</a>
        </div>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="ip-card">
            <table class="table ip-table">
                <thead><tr><th>Student</th><th>Program</th><th>Supervisor</th><th>Progress</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($enrolments as $e)
                    <tr>
                        <td>{{ optional($e->student)->name }}</td>
                        <td>
                            {{ optional($e->program)->displayName() }}
                            <small class="text-muted d-block">Days {{ $e->startCurriculumDay() }}–{{ $e->endCurriculumDay() }} ({{ $e->plannedDurationDays() }}d)</small>
                        </td>
                        <td>{{ optional($e->supervisor)->name ?: '—' }}</td>
                        <td>{{ $e->completed_count }}/{{ $e->plannedDurationDays() }}</td>
                        <td><span class="ip-badge">{{ $e->status }}</span></td>
                        <td>
                            <a class="btn btn-sm btn-link" href="{{ route('internship.enrol.edit', $e->id) }}">Place</a>
                            @if($e->status === 'active')
                                <form method="POST" action="{{ route('internship.enrol.pause', $e->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link">Pause</button></form>
                            @elseif($e->status === 'paused')
                                <form method="POST" action="{{ route('internship.enrol.resume', $e->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-link">Resume</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $enrolments->links() }}
        </div>
    </div>
</section>
@endsection

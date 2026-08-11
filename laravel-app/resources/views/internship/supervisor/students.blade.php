@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
            <h1 class="ip-title mb-0">My interns</h1>
            <div class="d-flex flex-wrap" style="gap:8px;">
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.dashboard') }}">Supervisor Home</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}">Grade Queue</a>
            </div>
        </div>
        <p class="ip-meta mb-3">Place each intern on a calendar start date and curriculum day (not necessarily day 1).</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="ip-card">
            <table class="table ip-table">
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Curriculum</th>
                    <th>Progress</th>
                    <th>Starts</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($enrolments as $e)
                    <tr>
                        <td>{{ optional($e->student)->name }}</td>
                        <td>{{ optional($e->program)->displayName() }}</td>
                        <td>Day {{ $e->startCurriculumDay() }}–{{ $e->endCurriculumDay() }}</td>
                        <td>{{ $e->completed_count }}/{{ $e->plannedDurationDays() }}</td>
                        <td>{{ $e->start_date ?: '—' }}</td>
                        <td><span class="ip-badge">{{ $e->status }}</span></td>
                        <td><a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.place', $e->id) }}">Place / adjust</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No students assigned to you yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $enrolments->links() }}
        </div>
    </div>
</section>
@endsection

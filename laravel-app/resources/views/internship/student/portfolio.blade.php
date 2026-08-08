@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.student.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Internship Portfolio</h1>
        @if(!$enrolment)
            <div class="ip-card">No enrolment found.</div>
        @else
            <div class="ip-card">
                <strong>{{ optional($enrolment->program)->displayName() ?? '' }}</strong>
                <div class="ip-meta">Passed tasks: {{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }} · {{ $enrolment->plannedDurationDays() }}-day placement · Status: {{ $enrolment->status }}</div>
            </div>
            <div class="ip-card">
                <table class="table ip-table">
                    <thead><tr><th>#</th><th>Title</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($enrolment->assignments as $a)
                        <tr>
                            <td>{{ $a->progression_day }}</td>
                            <td>{{ optional($a->task)->title }}</td>
                            <td><span class="ip-badge active">{{ $a->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No passed tasks yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection

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
            @include('internship.student.partials.supervisors', ['supervisors' => $supervisors ?? []])
            <div class="ip-card">
                <table class="table ip-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Task status</th>
                            <th>Grade</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($enrolment->assignments as $a)
                        @php
                            $grade = optional(optional($a->latestSubmission)->grades)->first();
                        @endphp
                        <tr>
                            <td>{{ $a->progression_day }}</td>
                            <td>{{ optional($a->task)->title }}</td>
                            <td><span class="ip-badge {{ $a->status === 'passed' ? 'active' : (in_array($a->status, ['submitted', 'revision_required'], true) ? 'warn' : 'blue') }}">{{ str_replace('_', ' ', $a->status) }}</span></td>
                            <td>
                                @if($grade)
                                    {{ $grade->score }}/100
                                    · {{ $a->status === 'passed' ? 'Accepted' : 'Revision required' }}
                                    @if($grade->auto_accepted)<span class="ip-badge warn">Auto</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td><a href="{{ route('internship.student.task', $a->id) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No tasks released yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection

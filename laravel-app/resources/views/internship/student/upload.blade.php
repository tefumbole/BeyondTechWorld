@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <h1 class="ip-title"><i class="dripicons-upload"></i> Upload Task</h1>
        <p class="ip-meta mb-3">Open a task below, attach your screenshots or PDF, then submit. Work already sent sits in Awaiting grading until your supervisor reviews it.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        @include('internship.student.partials.student-nav', ['activeNav' => 'upload', 'hideOpenTask' => true])

        @if(!$enrolment)
            <div class="ip-card">
                <h5 style="color:#0b3f90;font-weight:700;">No active placement</h5>
                <p class="mb-0 text-muted">Ask an Internship Administrator to enrol you before you can upload work.</p>
            </div>
        @else
            @if($awaiting->count())
                <div class="ip-card ip-pending">
                    <h5 style="font-weight:700;color:#c2410c;">Awaiting grading ({{ $awaiting->count() }})</h5>
                    <p class="ip-meta">These uploads are with your supervisor. You cannot replace them until they accept or request a revision.</p>
                    @foreach($awaiting as $row)
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;padding:.65rem 0;border-top:1px solid #fde68a;">
                            <div>
                                <strong>Task #{{ $row->progression_day }} — {{ optional($row->task)->title ?: 'Task' }}</strong>
                                <div class="ip-meta">Submitted · waiting for supervisor review</div>
                            </div>
                            <a class="ip-btn ip-btn-outline" href="{{ route('internship.student.task', $row->id) }}">View submission</a>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Ready to upload</h5>
                @forelse($uploadable as $row)
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;padding:.75rem 0;border-top:1px solid #eef2f7;">
                        <div>
                            <strong>Task #{{ $row->progression_day }} — {{ optional($row->task)->title ?: 'Task' }}</strong>
                            <div class="ip-meta">
                                Status: {{ str_replace('_', ' ', $row->status) }}
                                @if($row->scheduled_work_date)
                                    · Scheduled {{ optional($row->scheduled_work_date)->format('D d M Y') ?: $row->scheduled_work_date }}
                                @endif
                            </div>
                        </div>
                        <a class="ip-btn" href="{{ route('internship.student.task', $row->id) }}#ip-upload">
                            <i class="dripicons-upload"></i>
                            @if($row->status === 'revision_required') Fix and re-upload
                            @else Open task &amp; upload
                            @endif
                        </a>
                    </div>
                @empty
                    <p class="mb-0 text-muted">
                        @if($awaiting->count())
                            Nothing else is open for upload. Your current work is awaiting grading.
                        @else
                            No task is open for upload yet.
                            <a href="{{ route('internship.student.dashboard') }}">Request your task</a>
                            from My Internship if today’s work has not arrived.
                        @endif
                    </p>
                @endforelse
            </div>
        @endif
    </div>
</section>
@endsection

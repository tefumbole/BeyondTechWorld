@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <h1 class="ip-title">Internship Administration</h1>
        <p class="ip-meta mb-3">180-day practical programs, enrolments, and grading oversight.</p>
        <div class="ip-nav">
            <a class="ip-btn" href="{{ route('internship.programs') }}">Programs</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks') }}">Task Manager</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.import') }}">Import curriculum</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.enrolments') }}">Enrolments</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.enrol.create') }}">Enrol student</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}">Grade queue</a>
            <a class="ip-btn ip-btn-outline" href="{{ route('internship.reports') }}">Reports</a>
        </div>
        <div class="row">
            @foreach([
                ['Published programs', $stats['programs']],
                ['Active enrolments', $stats['active_enrolments']],
                ['Completed', $stats['completed']],
                ['Awaiting review', $stats['pending_review']],
            ] as $card)
                <div class="col-md-3 mb-3">
                    <div class="ip-card mb-0">
                        <div class="ip-meta">{{ $card[0] }}</div>
                        <strong style="font-size:1.75rem;color:#0b3f90;">{{ $card[1] }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

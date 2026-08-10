@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1100px;">
        <h1 class="ip-title">Internships</h1>
        <p class="ip-meta mb-4">Manage programs, supervisors, and accepted interns from one place.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.programs') }}" class="ip-hub-card">
                    <div class="ip-hub-icon"><i class="fa fa-book"></i></div>
                    <h3>Programs</h3>
                    <p>View and edit the 180-day curriculum tracks.</p>
                    <strong class="ip-hub-stat">{{ $stats['programs'] }} published</strong>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.supervisors') }}" class="ip-hub-card">
                    <div class="ip-hub-icon"><i class="fa fa-user-tie"></i></div>
                    <h3>Supervisors</h3>
                    <p>People assigned to supervise internship placements.</p>
                    <strong class="ip-hub-stat">{{ $stats['supervisors'] }} supervisors</strong>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="{{ route('internship.interns') }}" class="ip-hub-card">
                    <div class="ip-hub-icon"><i class="fa fa-users"></i></div>
                    <h3>Interns</h3>
                    <p>Accepted or hired applicants — assign programs and supervisors.</p>
                    <strong class="ip-hub-stat">{{ $stats['interns_ready'] }} ready to assign</strong>
                </a>
            </div>
        </div>

        <div class="ip-card mt-2">
            <div class="ip-meta mb-2">More tools</div>
            <div class="ip-nav mb-0">
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.tasks') }}">Task Manager</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.enrolments') }}">Enrolments</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.import') }}">Import curriculum</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}">Grade queue</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.reports') }}">Reports</a>
            </div>
        </div>

        <div class="row mt-3">
            @foreach([
                ['Active placements', $stats['active_enrolments']],
                ['Completed', $stats['completed']],
                ['Awaiting review', $stats['pending_review']],
            ] as $card)
                <div class="col-md-4 mb-3">
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

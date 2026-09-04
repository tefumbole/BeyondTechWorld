@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1180px;">
        <h1 class="ip-title"><i class="dripicons-meter"></i> Dashboard</h1>
        <p class="ip-meta mb-3">Your hours this week, and shortcuts into Supervisor and TimeSheets.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="row">
            <div class="col-md-4 col-lg-3 mb-3">
                <div class="ip-stat-tile ip-stat-blue">
                    <div class="ip-meta">Hours this week</div>
                    <strong>{{ number_format($weekScore['logged'], 1) }}</strong>
                    <div class="ip-meta">of {{ number_format($weekScore['expected'], 1) }}h</div>
                </div>
            </div>
            <div class="col-md-4 col-lg-3 mb-3">
                <div class="ip-stat-tile ip-stat-green">
                    <div class="ip-meta">Total hours</div>
                    <strong>{{ number_format($totalHours, 1) }}</strong>
                </div>
            </div>
            <div class="col-md-4 col-lg-3 mb-3">
                <a href="{{ route('internship.supervisor.students') }}" class="ip-stat-tile ip-stat-blue" style="display:block;text-decoration:none;color:#fff;">
                    <div class="ip-meta">My interns</div>
                    <strong>{{ (int) $internCount }}</strong>
                    <div class="ip-meta">{{ (int) $activeCount }} active</div>
                </a>
            </div>
            <div class="col-md-4 col-lg-3 mb-3">
                <a href="{{ route('internship.supervisor.index') }}" class="ip-stat-tile ip-stat-orange" style="display:block;text-decoration:none;color:#fff;">
                    <div class="ip-meta">To grade</div>
                    <strong>{{ (int) $pendingGrades }}</strong>
                    <div class="ip-meta">{{ (int) $pendingGrades === 1 ? 'submission waiting' : 'submissions waiting' }}</div>
                </a>
            </div>
        </div>

        <div class="ip-card">
            <h5 style="font-weight:700;color:#0b3f90;">Open a section</h5>
            <div class="ip-nav mb-0">
                <a class="ip-btn" href="{{ route('internship.supervisor.dashboard') }}"><i class="dripicons-user-group"></i> Supervisor</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.students') }}"><i class="dripicons-graduation"></i> My Interns</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.index') }}"><i class="dripicons-document-edit"></i> Grade Queue</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('timesheet.fill') }}"><i class="dripicons-clock"></i> Fill Time Sheet</a>
                <a class="ip-btn ip-btn-outline" href="{{ route('timesheet.working-week') }}"><i class="dripicons-calendar"></i> Working Week</a>
            </div>
        </div>
    </div>
</section>
@endsection

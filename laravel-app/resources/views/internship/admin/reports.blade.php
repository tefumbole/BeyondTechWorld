@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Internship reports</h1>
        <div class="ip-card">
            <table class="table ip-table">
                <thead><tr><th>Student</th><th>Program</th><th>Supervisor</th><th>Progress</th><th>Status</th><th>Last release</th></tr></thead>
                <tbody>
                @foreach($rows as $e)
                    <tr>
                        <td>{{ optional($e->student)->name }}</td>
                        <td>{{ optional($e->program)->code }}</td>
                        <td>{{ optional($e->supervisor)->name ?: '—' }}</td>
                        <td>{{ $e->completed_count }}/{{ $e->plannedDurationDays() }}</td>
                        <td>{{ $e->status }}</td>
                        <td>{{ $e->last_release_date ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

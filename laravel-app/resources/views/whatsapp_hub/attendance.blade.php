@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">Attendance operations</h1>
        <p class="wa-sub">WhatsApp check-in uses the existing attendance and timesheet records.</p>
        <div class="row">
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Checked in now</div><p class="wa-stat">{{ $metrics['checked_in_now'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Field staff on site</div><p class="wa-stat">{{ $metrics['field_on_site'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Missing check-out</div><p class="wa-stat">{{ $metrics['missing_checkout'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Location review</div><p class="wa-stat">{{ $metrics['location_review'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Pending corrections</div><p class="wa-stat">{{ $metrics['pending_corrections'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Failed actions</div><p class="wa-stat">{{ $metrics['failures'] }}</p></div></div>
        </div>
        <div class="wa-card">
            <h5>Open sessions</h5>
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Employee</th><th>Check-in</th><th>Assignment</th>@if($canLocation)<th>Location</th>@endif</tr></thead>
                <tbody>
                @forelse($open as $row)
                    <tr>
                        <td>{{ $row->date }}</td>
                        <td>{{ $row->employee_id ?: ('Intern '.$row->intern_user_id) }}</td>
                        <td>{{ $row->checkin }}</td>
                        <td>{{ $row->event_assignment_id ?: '—' }}</td>
                        @if($canLocation)
                            <td>{{ $row->location_status ?: '—' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5">Nobody is checked in.</td></tr>
                @endforelse
                </tbody>
            </table>
            <a href="{{ url('admin/attendance') }}">Open the attendance register</a>
        </div>
        <div class="wa-card">
            <h5>Correction requests</h5>
            @forelse($corrections as $row)
                <div class="small mb-2">
                    #{{ $row->id }} {{ $row->status }} — {{ $row->reason }}
                    @if($canCorrect && $row->status === 'PENDING' && $row->attendance_id)
                        <form method="POST" action="{{ route('whatsapp.attendance.corrections.approve', $row->id) }}" class="form-inline mt-1">
                            @csrf
                            <input type="time" name="checkout" class="form-control form-control-sm mr-1" required>
                            <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">None.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection

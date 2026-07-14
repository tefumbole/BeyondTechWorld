@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h4>Event Timesheets</h4>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <form method="GET" class="mb-3"><select name="status" class="form-control d-inline-block w-auto" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach(['submitted','approved','rejected','draft'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
    </select></form>
    <div class="table-responsive card"><table class="table mb-0">
        <thead style="background:#0b3f90;color:#fff"><tr><th>Event</th><th>Worker</th><th>Days</th><th>Hours</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($timesheets as $ts)
                <tr>
                    <td>{{ $ts->event->name }}</td>
                    <td>{{ $ts->workerProfile->displayName() }}</td>
                    <td>{{ $ts->total_days }}</td>
                    <td>{{ $ts->total_hours }}</td>
                    <td>{{ $ts->statusLabel() }}</td>
                    <td>
                        @if($ts->status==='submitted' && in_array('event_timesheets.approve',$all_permission))
                            <form method="POST" action="{{ route('events.timesheets.approve', $ts->id) }}" class="d-inline">@csrf<button class="btn btn-xs btn-success">Approve</button></form>
                            <button class="btn btn-xs btn-danger" data-toggle="modal" data-target="#reject-{{ $ts->id }}">Reject</button>
                            <div class="modal fade" id="reject-{{ $ts->id }}"><div class="modal-dialog"><form method="POST" action="{{ route('events.timesheets.reject', $ts->id) }}">@csrf<div class="modal-content"><div class="modal-body"><textarea name="rejection_reason" class="form-control" required placeholder="Reason"></textarea></div><div class="modal-footer"><button class="btn btn-danger">Reject</button></div></div></form></div></div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table><div class="card-footer">{{ $timesheets->links() }}</div></div>
</div></section>
@endsection

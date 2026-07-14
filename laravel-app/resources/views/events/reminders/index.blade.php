@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h4>Event Reminders</h4>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="table-responsive card"><table class="table mb-0">
        <thead style="background:#0b3f90;color:#fff"><tr><th>Event</th><th>When</th><th>Recipients</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @foreach($reminders as $r)
                <tr>
                    <td>{{ $r->event->name }} <code>{{ $r->event->reference_no }}</code></td>
                    <td>{{ $r->remind_at->format('d M Y H:i') }}</td>
                    <td>{{ $r->recipient_type }}</td>
                    <td>{{ $r->sent_at ? 'Sent' : ($r->send_error ? 'Failed' : 'Pending') }}</td>
                    <td>@if(!$r->sent_at)<form method="POST" action="{{ route('events.reminders.destroy', $r->id) }}">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Cancel</button></form>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table><div class="card-footer">{{ $reminders->links() }}</div></div>
</div></section>
@endsection

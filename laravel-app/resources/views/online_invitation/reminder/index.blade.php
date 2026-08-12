@extends('layout.main')
@section('content')
@php $oiTab = 'reminders'; @endphp
@include('online_invitation.partials.tabs')
<section class="container-fluid">
    <h3>Event invitation reminders</h3>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="row">
        <div class="col-md-5">
            <div class="card card-body mb-4">
                <h5>Schedule reminder</h5>
                <form method="POST" action="{{ route('online_invitation.reminders.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Event *</label>
                        <select name="event_id" class="form-control" required>
                            @foreach($events as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Remind at *</label>
                        <input type="datetime-local" name="remind_at" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Audience *</label>
                        <select name="audience" class="form-control" required>
                            <option value="accepted">Accepted (attending)</option>
                            <option value="sent">All sent invitations</option>
                            <option value="all">All invitations for event</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message (optional)</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Custom WhatsApp text..."></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Schedule</button>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Event</th><th>When</th><th>Audience</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($reminders as $r)
                        <tr>
                            <td>{{ optional($r->event)->name }}</td>
                            <td>{{ optional($r->remind_at)->format('d M Y H:i') }}</td>
                            <td>{{ $r->audience }}</td>
                            <td>{{ $r->status }}</td>
                            <td>
                                @if($r->status === 'scheduled')
                                    <form method="POST" action="{{ route('online_invitation.reminders.cancel', $r->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No reminders scheduled.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $reminders->links() }}
        </div>
    </div>
</section>
@endsection

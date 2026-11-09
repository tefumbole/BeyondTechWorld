@extends('layout.main')
@section('content')
@include('events.partials.modern')
<section class="forms events-shell"><div class="container-fluid">
    <div class="ev-page-head"><div><div class="ev-kicker">Events</div><h4>Event Reminders</h4></div></div>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(in_array('event_reminders.create', $all_permission))
        <div class="card mb-3"><div class="card-body">
            <h5 class="mb-2">Schedule a reminder</h5>
            <p class="small text-muted">Choose the event and when to send. The message is a reminder only. Nobody is asked to accept.</p>
            <form method="POST" action="{{ route('events.reminders.schedule') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Event</label>
                        <select name="event_id" class="form-control" required>
                            <option value="">Select an event</option>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" {{ old('event_id') === (string) $event->id ? 'selected' : '' }}>{{ $event->name }} @if($event->reference_no)({{ $event->reference_no }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Remind at</label>
                        <input type="datetime-local" name="remind_at" class="form-control" required value="{{ old('remind_at') }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Recipients</label>
                        <select name="recipient_type" class="form-control">
                            <option value="all_workers">All assigned workers</option>
                            <option value="client">Event client</option>
                            <option value="custom">Custom phone</option>
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Custom phone</label>
                        <input type="text" name="recipient_phone" class="form-control" value="{{ old('recipient_phone') }}" placeholder="6XX XXX XXX">
                    </div>
                    <div class="col-md-10 form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="2" placeholder="What they should remember">{{ old('message') }}</textarea>
                    </div>
                    <div class="col-md-2 d-flex align-items-end form-group">
                        <button type="submit" class="btn btn-primary btn-block">Schedule</button>
                    </div>
                </div>
            </form>
        </div></div>
    @endif
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

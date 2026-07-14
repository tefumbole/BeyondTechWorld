<div class="card">
    <div class="card-header font-weight-bold">Event Reminders</div>
    <div class="card-body">
        <form method="POST" action="{{ route('events.reminders.store', $event->id) }}" class="mb-4">
            @csrf
            <div class="row">
                <div class="col-md-4 form-group">
                    <label>Remind at</label>
                    <input type="datetime-local" name="remind_at" class="form-control" required>
                </div>
                <div class="col-md-3 form-group">
                    <label>Recipients</label>
                    <select name="recipient_type" class="form-control">
                        <option value="all_workers">All assigned workers</option>
                        <option value="client">Event client</option>
                        <option value="custom">Custom phone</option>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Custom phone (if custom)</label>
                    <input type="text" name="recipient_phone" class="form-control" placeholder="+250...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    @if(in_array('event_reminders.create', $all_permission))
                        <button type="submit" class="btn btn-primary btn-block">Schedule</button>
                    @endif
                </div>
                <div class="col-12 form-group">
                    <label>Message (optional)</label>
                    <textarea name="message" class="form-control" rows="2" placeholder="Extra reminder text…"></textarea>
                </div>
            </div>
        </form>

        <table class="table table-sm">
            <thead><tr><th>When</th><th>Recipients</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($event->reminders as $r)
                    <tr>
                        <td>{{ $r->remind_at->format('d M Y H:i') }}</td>
                        <td>{{ $r->recipient_type }}</td>
                        <td>
                            @if($r->sent_at)
                                <span class="text-success">Sent {{ $r->sent_at->format('d M H:i') }}</span>
                            @elseif($r->send_error)
                                <span class="text-danger" title="{{ $r->send_error }}">Failed</span>
                            @else
                                <span class="text-muted">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if(!$r->sent_at && in_array('event_reminders.create', $all_permission))
                                <form method="POST" action="{{ route('events.reminders.destroy', $r->id) }}" class="d-inline" onsubmit="return confirm('Cancel this reminder?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">No reminders scheduled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header font-weight-bold">Labour Payments</div>
    <div class="card-body">
        @if($event->assignments->isEmpty())
            <p class="text-muted">No workers assigned yet.</p>
        @else
            <form method="POST" action="{{ route('events.payments.create', $event->id) }}" class="mb-4 border-bottom pb-3">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-4 form-group mb-0">
                        <label>Worker assignment</label>
                        <select name="assignment_id" class="form-control" required>
                            @foreach($event->assignments as $a)
                                <option value="{{ $a->id }}">{{ $a->workerProfile->displayName() }} — {{ number_format($a->expected_total) }} XAF</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <label>Amount (XAF, blank = calculated)</label>
                        <input type="number" name="amount" class="form-control" min="1">
                    </div>
                    <div class="col-md-3 form-group mb-0">
                        <label>Mobile money number</label>
                        <input type="text" name="mobile_money_number" class="form-control">
                    </div>
                    <div class="col-md-2">
                        @if(in_array('event_payments.create', $all_permission))
                            <button type="submit" class="btn btn-primary btn-block">Create payment</button>
                        @endif
                    </div>
                </div>
            </form>

            <table class="table table-sm">
                <thead style="background:#0b3f90;color:#fff;">
                    <tr><th>Ref</th><th>Worker</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($event->payments as $p)
                        <tr>
                            <td><code>{{ $p->reference_no }}</code></td>
                            <td>{{ $p->workerProfile->displayName() }}</td>
                            <td>{{ number_format($p->amount) }} XAF</td>
                            <td><span class="badge badge-{{ $p->status === 'paid' ? 'success' : 'warning' }}">{{ $p->statusLabel() }}</span></td>
                            <td>
                                @if($p->status === 'pending' && in_array('event_payments.approve', $all_permission))
                                    <form method="POST" action="{{ route('events.payments.mark-paid', $p->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-success">Mark paid & send receipt</button>
                                    </form>
                                @endif
                                @if($p->receipt_path)
                                    <a href="{{ url($p->receipt_path) }}" target="_blank" class="btn btn-xs btn-light">Receipt</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>

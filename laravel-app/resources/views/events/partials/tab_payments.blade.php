@php
    $openSchedules = [];
    $scheduledTotal = 0;
    $byDate = [];
    foreach (($paymentSchedules ?? []) as $scheduleRow) {
        if (! in_array($scheduleRow->status, ['pending_approval', 'scheduled', 'paying', 'failed'], true)) {
            continue;
        }
        $openSchedules[] = $scheduleRow;
        $scheduledTotal += (int) $scheduleRow->amount;
        $dateKey = $scheduleRow->pay_at ? $scheduleRow->pay_at->format('Y-m-d') : 'none';
        if (! isset($byDate[$dateKey])) {
            $byDate[$dateKey] = [
                'label' => $scheduleRow->pay_at ? $scheduleRow->pay_at->format('d M Y') : 'No date',
                'amount' => 0,
            ];
        }
        $byDate[$dateKey]['amount'] += (int) $scheduleRow->amount;
    }
    $dueShown = isset($dueTotal) ? (int) $dueTotal : 0;
    $paidShown = isset($paidTotal) ? (int) $paidTotal : 0;
@endphp
<div class="card mb-3">
    <div class="card-header font-weight-bold">Payment schedules</div>
    <div class="card-body">
        <div class="ev-money">
            <div><span>Total due</span><strong>{{ number_format($dueShown) }} XAF</strong></div>
            <div><span>Already paid</span><strong>{{ number_format($paidShown) }} XAF</strong></div>
            <div><span>Still to schedule</span><strong>{{ number_format(max(0, $dueShown - $paidShown - $scheduledTotal)) }} XAF</strong></div>
            <div><span>Scheduled</span><strong>{{ number_format($scheduledTotal) }} XAF</strong></div>
        </div>
        @if(count($byDate))
            <p class="mb-3">
                @foreach($byDate as $dateRow)
                    <span class="badge badge-light border mr-1 mb-1" style="font-size:14px;padding:8px 10px;">{{ $dateRow['label'] }}: {{ number_format($dateRow['amount']) }} XAF</span>
                @endforeach
            </p>
        @endif

        @if(!empty($isEventAdmin))
            <form method="POST" action="{{ route('events.auto-pay.selection', $event->id) }}" class="border rounded p-3 mb-3" id="crew-pay">
                @csrf
                <div class="font-weight-bold mb-2">Schedule the people you select</div>
                <label class="d-block mb-2"><input type="checkbox" id="crew-all"> Select all</label>
                @foreach($event->assignments as $assignment)
                    @if($assignment->acceptance_status === 'rejected')
                        @continue
                    @endif
                    @php
                        $todayDue = isset($dayDues[$assignment->id]) ? (int) $dayDues[$assignment->id] : 0;
                        $still = isset($payRooms[$assignment->id]) ? (int) $payRooms[$assignment->id] : 0;
                    @endphp
                    <label class="d-block mb-1">
                        <input type="checkbox" class="crew-pick" name="assignment_ids[]" value="{{ $assignment->id }}">
                        {{ $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'Person' }}
                        @if($todayDue > 0)
                            — {{ number_format($todayDue) }} XAF for today
                        @else
                            — {{ number_format($still) }} XAF still to schedule
                        @endif
                    </label>
                @endforeach
                <div class="form-row align-items-end mt-3">
                    <div class="col-md-3 form-group mb-md-0">
                        <label class="d-block"><input type="radio" name="mode" value="percent" checked> Percentage</label>
                        <input type="number" name="percent" id="pay-percent" class="form-control" min="1" max="100" value="100">
                    </div>
                    <div class="col-md-3 form-group mb-md-0">
                        <label class="d-block"><input type="radio" name="mode" value="day"> One working day</label>
                        <input type="date" name="work_date" id="pay-day" class="form-control">
                    </div>
                    <div class="col-md-3 form-group mb-md-0">
                        <label>Send on</label>
                        <input type="datetime-local" name="pay_at" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="do" value="schedule" class="btn btn-primary btn-block">Schedule selected</button>
                        <button type="submit" name="do" value="pay" id="pay-day-now" class="btn btn-outline-primary btn-block mt-2" onclick="return confirm('Pay only this working day for the selected people now?');">Pay this day now</button>
                    </div>
                </div>
                <p class="small text-muted mb-0 mt-2">A percentage uses what is still owed. A working day pays only that day's rate and food money, not earlier days.</p>
            </form>
            <script>
                document.getElementById('crew-all').addEventListener('change', function () {
                    var on = this.checked;
                    document.querySelectorAll('.crew-pick').forEach(function (box) { box.checked = on; });
                });
                document.getElementById('crew-pay').addEventListener('change', function (event) {
                    if (event.target.name !== 'mode') return;
                    var day = event.target.value === 'day';
                    document.getElementById('pay-day').required = day;
                    document.getElementById('pay-percent').required = !day;
                    document.getElementById('pay-day-now').style.display = day ? '' : 'none';
                });
                document.getElementById('pay-day-now').style.display = 'none';
            </script>
            <form method="POST" action="{{ route('events.schedule-pay', $event->id) }}" class="border rounded p-3 mb-3">
                @csrf
                <div class="font-weight-bold mb-2">Add another schedule</div>
                <div class="form-row align-items-end">
                    <div class="col-md-4 form-group mb-md-0">
                        <label>Person</label>
                        <select name="assignment_id" class="form-control" required>
                            <option value="">Choose a person</option>
                            @foreach($event->assignments as $assignment)
                                @if($assignment->acceptance_status === 'rejected')
                                    @continue
                                @endif
                                <option value="{{ $assignment->id }}">{{ $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'Person' }} — {{ number_format(isset($payRooms[$assignment->id]) ? $payRooms[$assignment->id] : 0) }} XAF remaining</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group mb-md-0">
                        <label>Amount</label>
                        <input type="number" name="amount" class="form-control" min="100" required>
                    </div>
                    <div class="col-md-3 form-group mb-md-0">
                        <label>When</label>
                        <input type="datetime-local" name="pay_at" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-block">Add schedule</button>
                    </div>
                </div>
                <p class="small text-muted mb-0 mt-2">This adds a date. It does not remove the schedules already listed. The amount cannot be more than what is remaining.</p>
            </form>
        @endif

        @if(!count($openSchedules))
            <p class="text-muted mb-0">No payment is scheduled yet.</p>
        @else
            @if(!empty($isEventAdmin))
                <form id="schedule-bulk" method="POST" action="{{ route('events.auto-pay.selected', $event->id) }}" class="mb-2">
                    @csrf
                    <button type="submit" name="do" value="pay" class="btn btn-primary" onclick="return confirm('Pay the selected schedules now?');">Pay selected</button>
                    <button type="submit" name="do" value="delete" class="btn btn-outline-danger" onclick="return confirm('Delete the selected schedules?');">Delete selected</button>
                </form>
            @endif
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead style="background:#0b3f90;color:#fff;">
                        <tr>
                            @if(!empty($isEventAdmin))<th style="width:36px;"><input type="checkbox" id="sched-all" aria-label="Select all"></th>@endif
                            <th>Person</th>
                            <th>Amount due</th>
                            <th>This schedule</th>
                            <th>Remaining</th>
                            <th>When</th>
                            <th>Status</th>
                            @if(!empty($isEventAdmin))<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($openSchedules as $scheduleRow)
                            @php
                                $person = $scheduleRow->assignment && $scheduleRow->assignment->workerProfile
                                    ? $scheduleRow->assignment->workerProfile->displayName()
                                    : 'Everyone on the event';
                                $editable = !empty($isEventAdmin) && in_array($scheduleRow->status, ['pending_approval', 'scheduled', 'failed'], true);
                                $amountDue = $scheduleRow->assignment ? (int) $scheduleRow->assignment->expected_total : $dueShown;
                                $remaining = isset($payRooms[$scheduleRow->assignment_id]) ? (int) $payRooms[$scheduleRow->assignment_id] : max(0, $amountDue - (int) $scheduleRow->amount);
                            @endphp
                            <tr>
                                @if(!empty($isEventAdmin))
                                    <td>
                                        @if($editable)
                                            <input form="schedule-bulk" class="sched-pick" type="checkbox" name="ids[]" value="{{ $scheduleRow->id }}" aria-label="Select {{ $person }}">
                                        @endif
                                    </td>
                                @endif
                                <td>{{ $person }}</td>
                                <td>{{ number_format($amountDue) }}</td>
                                @if($editable)
                                    <td><input form="sched-{{ $scheduleRow->id }}" type="number" name="amount" class="form-control form-control-sm" min="100" value="{{ (int) $scheduleRow->amount }}" required></td>
                                    <td class="font-weight-bold">{{ number_format($remaining) }}</td>
                                    <td><input form="sched-{{ $scheduleRow->id }}" type="datetime-local" name="pay_at" class="form-control form-control-sm" value="{{ $scheduleRow->pay_at ? $scheduleRow->pay_at->format('Y-m-d\TH:i') : '' }}" required></td>
                                    <td><span class="badge badge-secondary">{{ str_replace('_', ' ', $scheduleRow->status) }}</span></td>
                                    <td class="text-nowrap">
                                        <form id="sched-{{ $scheduleRow->id }}" method="POST" action="{{ route('events.auto-pay.update', [$event->id, $scheduleRow->id]) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                        <button form="schedule-bulk" class="btn btn-sm btn-light" name="do" value="delete" onclick="document.querySelectorAll('.sched-pick').forEach(function (box) { box.checked = box.value === '{{ $scheduleRow->id }}'; }); return confirm('Delete this schedule?');">Delete</button>
                                    </td>
                                @else
                                    <td>{{ number_format((int) $scheduleRow->amount) }}</td>
                                    <td class="font-weight-bold">{{ number_format($remaining) }}</td>
                                    <td>{{ $scheduleRow->pay_at ? $scheduleRow->pay_at->format('d M Y H:i') : '—' }}</td>
                                    <td>{{ str_replace('_', ' ', $scheduleRow->status) }}</td>
                                    @if(!empty($isEventAdmin))<td></td>@endif
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(!empty($isEventAdmin))
                <script>
                    document.getElementById('sched-all').addEventListener('change', function () {
                        var on = this.checked;
                        document.querySelectorAll('.sched-pick').forEach(function (box) { box.checked = on; });
                    });
                </script>
            @endif
        @endif
    </div>
</div>
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

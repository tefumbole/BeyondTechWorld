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

        @php
            $payList = [];
            $payDates = [];
            foreach (($dueDays ?? []) as $dueDay) {
                $payList[] = [
                    'token' => 'day|'.$dueDay['assignment_id'].'|'.$dueDay['date'],
                    'name' => $dueDay['name'],
                    'date' => $dueDay['date'],
                    'amount' => (int) $dueDay['amount'],
                    'status' => 'Due',
                    'ref' => '',
                ];
                $payDates[$dueDay['date']] = \Carbon\Carbon::parse($dueDay['date'])->format('d M Y');
            }
            foreach ($openSchedules as $scheduleRow) {
                $workDate = (is_string($scheduleRow->result) && strpos($scheduleRow->result, 'work_date:') === 0)
                    ? substr($scheduleRow->result, 10, 10)
                    : ($scheduleRow->pay_at ? $scheduleRow->pay_at->format('Y-m-d') : '');
                $status = $scheduleRow->status === 'pending_approval' ? 'Pending' : ucfirst(str_replace('_', ' ', $scheduleRow->status));
                $payList[] = [
                    'token' => 'auto|'.$scheduleRow->id,
                    'name' => $scheduleRow->assignment && $scheduleRow->assignment->workerProfile
                        ? $scheduleRow->assignment->workerProfile->displayName()
                        : 'Everyone on the event',
                    'date' => $workDate,
                    'amount' => (int) $scheduleRow->amount,
                    'status' => $status,
                    'ref' => '',
                ];
                if ($workDate !== '') {
                    $payDates[$workDate] = \Carbon\Carbon::parse($workDate)->format('d M Y');
                }
            }
            foreach ($event->payments as $payment) {
                if ($payment->status !== 'pending') {
                    continue;
                }
                $noteDate = '';
                if (preg_match('/^(\d{4}-\d{2}-\d{2}) /', (string) $payment->notes, $noteMatch)) {
                    $noteDate = $noteMatch[1];
                }
                $payList[] = [
                    'token' => 'pay|'.$payment->id,
                    'name' => $payment->workerProfile ? $payment->workerProfile->displayName() : 'Person',
                    'date' => $noteDate,
                    'amount' => (int) $payment->amount,
                    'status' => 'Pending',
                    'ref' => $payment->reference_no,
                ];
                if ($noteDate !== '') {
                    $payDates[$noteDate] = \Carbon\Carbon::parse($noteDate)->format('d M Y');
                }
            }
            usort($payList, function ($a, $b) {
                $byDate = strcmp($a['date'], $b['date']);
                return $byDate !== 0 ? $byDate : strcmp($a['name'], $b['name']);
            });
            ksort($payDates);
        @endphp
        @if(!empty($isEventAdmin))
            <form method="POST" action="{{ route('events.pay-listed', $event->id) }}" class="ev-pick" id="crew-pay">
                @csrf
                <h3>Pending and due</h3>
                <p class="hint">Pick a date to see everyone due that day. Select the rows, then pay them or schedule them.</p>
                <div class="ev-day-pills" id="due-day-pills">
                    <button type="button" class="is-on" data-day="">All days</button>
                    @foreach($payDates as $dateKey => $dateLabel)
                        <button type="button" data-day="{{ $dateKey }}">{{ $dateLabel }}</button>
                    @endforeach
                </div>
                @if(!count($payList))
                    <p class="text-muted mb-0">No pending or due payments.</p>
                @else
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead style="background:#0b3f90;color:#fff;">
                                <tr>
                                    <th style="width:42px;"><input type="checkbox" id="crew-all" aria-label="Select all"></th>
                                    <th>Ref</th>
                                    <th>Person</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payList as $line)
                                    <tr class="due-row" data-day="{{ $line['date'] }}">
                                        <td><input type="checkbox" class="due-pick" name="lines[]" value="{{ $line['token'] }}"></td>
                                        <td>{{ $line['ref'] !== '' ? $line['ref'] : '—' }}</td>
                                        <td>{{ $line['name'] }}</td>
                                        <td>{{ $line['date'] !== '' ? \Carbon\Carbon::parse($line['date'])->format('d M Y') : '—' }}</td>
                                        <td>{{ number_format($line['amount']) }} XAF</td>
                                        <td><span class="ev-offer is-wait">{{ $line['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ev-send">
                        <label for="pay-at">Send a schedule on</label>
                        <input id="pay-at" type="datetime-local" name="pay_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="ev-pay-actions">
                        <button type="submit" name="do" value="schedule" class="btn btn-primary">Schedule selected</button>
                        <button type="submit" name="do" value="pay" class="btn btn-pay" onclick="return confirm('Pay the selected rows now?');">Pay selected</button>
                    </div>
                @endif
            </form>
            <script>
                (function () {
                    var pills = document.getElementById('due-day-pills');
                    var all = document.getElementById('crew-all');
                    if (!pills || !all) return;
                    var current = '';
                    function rows() { return document.querySelectorAll('.due-row'); }
                    pills.addEventListener('click', function (event) {
                        var button = event.target.closest('button');
                        if (!button) return;
                        current = button.getAttribute('data-day') || '';
                        pills.querySelectorAll('button').forEach(function (item) { item.classList.toggle('is-on', item === button); });
                        rows().forEach(function (row) {
                            var show = current === '' || row.getAttribute('data-day') === current;
                            row.style.display = show ? '' : 'none';
                            if (!show) {
                                var box = row.querySelector('.due-pick');
                                if (box) box.checked = false;
                            }
                        });
                        all.checked = false;
                    });
                    all.addEventListener('change', function () {
                        var on = this.checked;
                        rows().forEach(function (row) {
                            if (row.style.display === 'none') return;
                            var box = row.querySelector('.due-pick');
                            if (box) box.checked = on;
                        });
                    });
                })();
            </script>
        @elseif(!count($payList))
            <p class="text-muted mb-0">No pending or due payments.</p>
        @endif
        @php
            $paidRows = $event->payments->filter(function ($payment) {
                return $payment->status === 'paid';
            });
        @endphp
        @if($paidRows->count())
            <h3 class="mt-4" style="font-size:16px;font-weight:800;">Already paid</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead style="background:#0b3f90;color:#fff;">
                        <tr><th>Ref</th><th>Person</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($paidRows as $payment)
                            <tr>
                                <td><code>{{ $payment->reference_no }}</code></td>
                                <td>{{ $payment->workerProfile ? $payment->workerProfile->displayName() : 'Person' }}</td>
                                <td>{{ number_format($payment->amount) }} XAF</td>
                                <td><span class="ev-offer is-yes">Paid</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

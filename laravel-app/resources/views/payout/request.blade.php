@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    @include('payout.tabs', ['tab' => 'request'])
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

    <div class="pay-card">
        <div class="pay-card-head"><h2>Request for Payment</h2></div>
        <div class="pay-card-body">
            <p class="pay-help">Choose people from the system, or add a number. They receive a WhatsApp, in the name saved here, asking them to submit payment information. You can also copy the link.</p>
            <form method="POST" action="{{ route('payout.request.invite') }}" id="inviteForm">
                @csrf
                <div class="pay-search">
                    <label>People</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input type="search" id="inviteSearch" class="form-control" placeholder="Search by name or phone number" autocomplete="off">
                        <button class="pay-plus" type="button" id="inviteAddPhone" title="Add a number">+</button>
                    </div>
                    <div id="inviteHits" class="list-group" style="position:absolute;z-index:5;width:100%;max-height:240px;overflow:auto"></div>
                    <div id="invitePhoneRow" style="display:none;margin-top:8px">
                        <input type="tel" id="invitePhone" class="form-control" placeholder="Phone number, 6xxxxxxxx" autocomplete="off">
                    </div>
                </div>
                <div class="table-responsive" style="margin-top:14px">
                    <table class="pay-table">
                        <thead><tr><th>Name</th><th>Number</th><th></th></tr></thead>
                        <tbody id="inviteBody">
                            <tr id="inviteEmpty"><td colspan="3" class="pay-muted">No one selected yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="form-group" style="max-width:520px;margin-top:14px">
                    <label>Reason</label>
                    <input type="text" name="reason" class="form-control" maxlength="180" placeholder="Why this payment is being requested">
                </div>
                <button class="pay-go" type="submit">Send request</button>
            </form>
            <div class="pay-linkbox" style="margin-top:18px">
                <input type="text" id="requestUrl" class="form-control" readonly value="{{ $url }}">
                <button class="pay-go" type="button" id="copyLink">Copy link</button>
            </div>
        </div>
    </div>

    @if($review)
        <div class="pay-card">
            <div class="pay-card-head">
                <h2>{{ $review->requester_name }} @if($review->note)<span class="pay-muted">· {{ $review->note }}</span>@endif</h2>
                <span>
                    @if($reviewLines->whereIn('status', ['pending', 'failed'])->count() > 0)
                        <form method="POST" action="{{ route('payout.mass', $review->id) }}" style="display:inline">
                            @csrf
                            <button class="pay-open" type="submit" style="border:0;cursor:pointer" onclick="return confirm('Send this Mass Payout now? People already paid are left out.')">Mass Payout</button>
                        </form>
                    @endif
                    <a href="{{ route('payout.request') }}" style="margin-left:12px">Close</a>
                </span>
            </div>
            <div class="pay-card-body">
                @if($review->status === 'pending')
                    <p class="pay-help">Change an amount, or select names and delete them. Pay, Approve and Pay, and Mass Payout all use the same send, even when only one person is left. A number already paid is not sent again. Delete a failed payment if a retry should leave it out.</p>
                    <form method="POST" action="{{ route('payout.request.revise') }}" id="reviewForm">
                        @csrf
                        <input type="hidden" name="id" value="{{ $review->id }}">
                        <div class="table-responsive">
                            <table class="pay-table">
                                <thead>
                                    <tr>
                                        <th style="width:36px"><input type="checkbox" id="reviewAll" title="Select all"></th>
                                        <th>Name</th>
                                        <th>Number</th>
                                        <th>Amount (XAF)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reviewLines as $line)
                                        @php
                                            $tone = $line->status === 'paid' ? 'ok' : ($line->status === 'pending' ? 'wait' : 'no');
                                            $statusLabel = $line->status === 'paid' ? 'Paid' : ($line->status === 'pending' ? 'Waiting' : ($line->status === 'failed' ? 'Not paid' : 'Rejected'));
                                        @endphp
                                        <tr>
                                            <td>
                                                @if(in_array($line->status, ['pending', 'failed'], true))
                                                    <input type="checkbox" class="review-line" name="remove_ids[]" value="{{ $line->id }}">
                                                @endif
                                            </td>
                                            <td>{{ $line->person_name }}</td>
                                            <td>{{ $line->phone }}</td>
                                            <td>
                                                @if($line->status === 'pending')
                                                    <input type="number" class="form-control form-control-sm" name="amounts[{{ $line->id }}]" min="100" max="1000000" step="1" value="{{ $line->amount }}" style="max-width:140px">
                                                @else
                                                    {{ number_format($line->amount, 0, '.', ' ') }}
                                                @endif
                                            </td>
                                            <td>
                                                <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                                @if($line->error)<div class="pay-note">{{ $line->error }}</div>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pay-actions">
                            <button class="pay-go" type="submit" name="action" value="delete" formnovalidate style="background:#fdecec;color:#9b1c1c" id="reviewDelete">Delete</button>
                            <button class="pay-go" type="submit" name="action" value="pay">Approve and Pay</button>
                            <button class="pay-go" type="submit" name="action" value="reject" formnovalidate style="background:#fff;color:#9b1c1c;border:1px solid #f3c7c7" onclick="return confirm('Reject this request?')">Reject</button>
                        </div>
                    </form>
                    <script>
                    (function () {
                        var form = document.getElementById('reviewForm');
                        var all = document.getElementById('reviewAll');
                        var del = document.getElementById('reviewDelete');
                        if (!form || !del) return;
                        function boxes() { return form.querySelectorAll('.review-line'); }
                        if (all) {
                            all.addEventListener('change', function () {
                                boxes().forEach(function (box) { box.checked = all.checked; });
                            });
                        }
                        del.addEventListener('click', function (event) {
                            var count = 0;
                            boxes().forEach(function (box) { if (box.checked) count++; });
                            if (!count) {
                                event.preventDefault();
                                return;
                            }
                            if (!confirm('Delete ' + count + (count === 1 ? ' person?' : ' people?'))) {
                                event.preventDefault();
                            }
                        });
                    })();
                    </script>
                @else
                    @if($reviewLines->where('status', 'failed')->count() > 0)
                        <p class="pay-help">Select the payments that failed and delete them. A retry will not send a deleted payment.</p>
                    @endif
                    <form method="POST" action="{{ route('payout.drop') }}" id="failedForm">
                        @csrf
                        <div class="table-responsive">
                            <table class="pay-table">
                                <thead>
                                    <tr>
                                        <th style="width:36px"><input type="checkbox" id="failedAll" title="Select all failed"></th>
                                        <th>Name</th>
                                        <th>Number</th>
                                        <th>Amount (XAF)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reviewLines as $line)
                                        @php
                                            $tone = $line->status === 'paid' ? 'ok' : ($line->status === 'pending' ? 'wait' : 'no');
                                            $statusLabel = $line->status === 'paid' ? 'Paid' : ($line->status === 'pending' ? 'Waiting' : ($line->status === 'failed' ? 'Not paid' : 'Rejected'));
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($line->status === 'failed')
                                                    <input type="checkbox" class="failed-line" name="ids[]" value="{{ $line->id }}">
                                                @endif
                                            </td>
                                            <td>{{ $line->person_name }}</td>
                                            <td>{{ $line->phone }}</td>
                                            <td>{{ number_format($line->amount, 0, '.', ' ') }}</td>
                                            <td>
                                                <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                                @if($line->error)<div class="pay-note">{{ $line->error }}</div>@endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($reviewLines->where('status', 'failed')->count() > 0)
                            <div class="pay-actions">
                                <button class="pay-go" type="submit" style="background:#fdecec;color:#9b1c1c" id="failedDelete">Delete</button>
                            </div>
                        @endif
                    </form>
                    <script>
                    (function () {
                        var form = document.getElementById('failedForm');
                        var all = document.getElementById('failedAll');
                        var del = document.getElementById('failedDelete');
                        if (!form || !del) return;
                        function boxes() { return form.querySelectorAll('.failed-line'); }
                        if (all) {
                            all.addEventListener('change', function () {
                                boxes().forEach(function (box) { box.checked = all.checked; });
                            });
                        }
                        del.addEventListener('click', function (event) {
                            var count = 0;
                            boxes().forEach(function (box) { if (box.checked) count++; });
                            if (!count) {
                                event.preventDefault();
                                return;
                            }
                            if (!confirm('Delete ' + count + (count === 1 ? ' failed payment? A retry will not send it.' : ' failed payments? A retry will not send them.'))) {
                                event.preventDefault();
                            }
                        });
                    })();
                    </script>
                @endif
            </div>
        </div>
    @endif

    <div class="pay-card">
        <div class="pay-card-head"><h2>Requested payments</h2></div>
        <div class="table-responsive">
            <table class="pay-table">
                <thead>
                    <tr><th>When</th><th>People</th><th>Reason</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @php
                        $rows = collect();
                        foreach ($invites as $row) {
                            $rows->push(['at' => $row->created_at, 'kind' => 'invite', 'row' => $row]);
                        }
                        foreach ($submitted as $row) {
                            $rows->push(['at' => $row->created_at, 'kind' => 'submitted', 'row' => $row]);
                        }
                        $rows = $rows->sortByDesc(function ($item) {
                            return $item['at'] ? $item['at']->timestamp : 0;
                        })->values();
                    @endphp
                    @forelse($rows as $item)
                        @php $row = $item['row']; @endphp
                        <tr>
                            <td>
                                {{ $row->created_at ? $row->created_at->format('M j, Y H:i') : '' }}
                                @if($item['kind'] === 'invite' && $row->sent_at && $row->created_at && $row->sent_at->gt($row->created_at->copy()->addMinute()))
                                    <div class="pay-muted">Resent {{ $row->sent_at->format('M j, H:i') }}</div>
                                @endif
                            </td>
                            <td>
                                @if($item['kind'] === 'invite')
                                    @foreach((array) $row->people as $person)
                                        <div>{{ $person['name'] ?? '' }} <span class="pay-muted">{{ $person['phone'] ?? '' }}</span></div>
                                    @endforeach
                                @else
                                    @foreach($row->lines as $line)
                                        <div>{{ $line->person_name }} <span class="pay-muted">{{ number_format($line->amount, 0, '.', ' ') }} XAF</span></div>
                                    @endforeach
                                    @if($row->requester_name)
                                        <div class="pay-muted">From {{ $row->requester_name }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $item['kind'] === 'invite' ? $row->reason : $row->note }}</td>
                            <td>
                                @php
                                    $linePaid = $item['kind'] === 'submitted' ? $row->lines->where('status', 'paid')->count() : 0;
                                    $lineFailed = $item['kind'] === 'submitted' ? $row->lines->where('status', 'failed')->count() : 0;
                                    $linePending = $item['kind'] === 'submitted' ? $row->lines->where('status', 'pending')->count() : 0;
                                @endphp
                                @if($item['kind'] === 'invite')
                                    <span class="pay-pill pay-pill-wait">Asked</span>
                                @elseif($row->status === 'rejected')
                                    <span class="pay-pill pay-pill-no">Rejected</span>
                                @elseif($linePending > 0)
                                    <span class="pay-pill pay-pill-wait">Submitted</span>
                                @elseif($linePaid > 0 && $lineFailed === 0)
                                    <span class="pay-pill pay-pill-ok">Paid</span>
                                @elseif($lineFailed > 0 && $linePaid === 0)
                                    <span class="pay-pill pay-pill-no">Not paid</span>
                                @elseif($linePaid > 0)
                                    <span class="pay-pill pay-pill-wait">Partly paid</span>
                                @elseif($row->status === 'done')
                                    <span class="pay-pill pay-pill-ok">Paid</span>
                                @else
                                    <span class="pay-pill pay-pill-wait">Submitted</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                @if($item['kind'] === 'submitted' && ($linePending + $lineFailed) > 0)
                                    <form method="POST" action="{{ route('payout.mass', $row->id) }}">
                                        @csrf
                                        <button class="pay-open" type="submit" style="border:0;cursor:pointer;background:#e7eef8;color:#0b3f90" onclick="return confirm('Send this Mass Payout now? People already paid are left out.')">Mass Payout</button>
                                    </form>
                                @endif
                                @if($item['kind'] === 'submitted' && $lineFailed > 0)
                                    <a class="pay-open" href="{{ route('payout.request', ['review' => $row->id]) }}" style="background:#fdecec;color:#9b1c1c !important">Delete failed</a>
                                    <form method="POST" action="{{ route('payout.retry') }}">
                                        @csrf
                                        <input type="hidden" name="request_id" value="{{ $row->id }}">
                                        <button class="pay-open" type="submit" style="border:0;cursor:pointer" onclick="return confirm('Send the failed payments as a Mass Payout? One person is sent the same way as many.')">Retry</button>
                                    </form>
                                @endif
                                @if($item['kind'] === 'submitted' && $row->status === 'pending')
                                    <form method="POST" action="{{ route('payout.request.revise') }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $row->id }}">
                                        <button class="pay-open" type="submit" name="action" value="approve" style="border:0;cursor:pointer" onclick="return confirm('Approve and pay everyone still waiting on this request?')">Approve and Pay</button>
                                    </form>
                                    <a class="pay-open" href="{{ route('payout.request', ['review' => $row->id]) }}" style="background:#e7eef8;color:#0b3f90 !important">Edit</a>
                                @endif
                                <form method="POST" action="{{ route('payout.request.resend') }}">
                                    @csrf
                                    <input type="hidden" name="kind" value="{{ $item['kind'] }}">
                                    <input type="hidden" name="id" value="{{ $row->id }}">
                                    <button class="pay-open" type="submit" style="border:0;cursor:pointer">Resend</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pay-muted">No payment requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
<script>
(function () {
    var form = document.getElementById('inviteForm');
    var body = document.getElementById('inviteBody');
    var empty = document.getElementById('inviteEmpty');
    var hits = document.getElementById('inviteHits');
    var search = document.getElementById('inviteSearch');
    var phoneRow = document.getElementById('invitePhoneRow');
    var phoneInput = document.getElementById('invitePhone');
    var searchUrl = @json(route('payout.search'));
    var lookupUrl = @json(route('payout.lookup'));
    var added = {};
    function refresh() {
        empty.style.display = body.querySelectorAll('tr[data-key]').length ? 'none' : '';
    }
    function addPerson(key, name, phone, fields) {
        if (added[key]) return;
        added[key] = true;
        var row = document.createElement('tr');
        row.setAttribute('data-key', key);
        var nameCell = document.createElement('td');
        nameCell.textContent = name;
        var phoneCell = document.createElement('td');
        phoneCell.textContent = phone;
        fields.forEach(function (field) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = field.name;
            input.value = field.value;
            nameCell.appendChild(input);
        });
        var removeCell = document.createElement('td');
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-link text-danger';
        remove.textContent = 'Remove';
        remove.addEventListener('click', function () {
            delete added[key];
            row.remove();
            refresh();
        });
        removeCell.appendChild(remove);
        row.appendChild(nameCell);
        row.appendChild(phoneCell);
        row.appendChild(removeCell);
        body.appendChild(row);
        refresh();
    }
    var timer = null;
    search.addEventListener('input', function () {
        clearTimeout(timer);
        var q = search.value.trim();
        if (q.length < 1) {
            hits.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    hits.innerHTML = '';
                    (data.people || []).forEach(function (person) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'list-group-item list-group-item-action';
                        button.textContent = person.name + ' (' + person.phone + ')';
                        button.addEventListener('click', function () {
                            var idName = person.kind === 'user' ? 'user_id[]' : 'customer_id[]';
                            addPerson(person.kind + ':' + person.id, person.name, person.phone, [{name: idName, value: person.id}]);
                            hits.innerHTML = '';
                            search.value = '';
                        });
                        hits.appendChild(button);
                    });
                    if (!hits.children.length) {
                        var none = document.createElement('div');
                        none.className = 'list-group-item text-muted';
                        none.textContent = 'No matching name or number';
                        hits.appendChild(none);
                    }
                });
        }, 200);
    });
    document.getElementById('inviteAddPhone').addEventListener('click', function () {
        phoneRow.style.display = 'block';
        phoneInput.focus();
    });
    var phoneWait = null;
    phoneInput.addEventListener('input', function () {
        clearTimeout(phoneWait);
        var raw = phoneInput.value.trim();
        if (raw.replace(/\D/g, '').length < 9) return;
        phoneWait = setTimeout(function () {
            fetch(lookupUrl + '?phone=' + encodeURIComponent(raw), {headers: {Accept: 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (info) {
                    if (!info || !info.ok) return;
                    var label = info.name ? info.name : info.phone;
                    addPerson('phone:' + info.phone, label, info.phone, [{name: 'extra_phone[]', value: info.phone}]);
                    phoneInput.value = '';
                });
        }, 400);
    });
    form.addEventListener('submit', function (event) {
        if (!body.querySelector('tr[data-key]')) event.preventDefault();
    });
    document.getElementById('copyLink').addEventListener('click', function () {
        var input = document.getElementById('requestUrl');
        var button = this;
        input.focus();
        input.select();
        var done = function () { button.textContent = 'Copied'; };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done);
            return;
        }
        document.execCommand('copy');
        done();
    });
})();
</script>
@endsection

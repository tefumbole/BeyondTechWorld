@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    @include('payout.tabs', ['tab' => 'donations'])
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
    <div class="pay-balance">
        <div>
            <div class="pay-kicker">Mobile Money balance</div>
            <div class="pay-total">{{ $campayBalance !== null ? $campayBalance.' XAF' : '— XAF' }}</div>
            <div class="pay-sub">
                @if($campayBalance === null)
                    Campay did not answer. Try again in a moment.
                @else
                    Live balance from Campay. Received here {{ number_format($paidTotal, 0, '.', ' ') }} XAF · Pending {{ number_format($pendingTotal, 0, '.', ' ') }} XAF
                @endif
            </div>
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-head"><h2>Mobile Payments</h2></div>
        <form method="POST" action="{{ route('donations.delete_selection') }}" id="donationsForm">
            @csrf
            <div class="table-responsive">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="donationsAll" title="Select all"></th>
                            <th>When</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $methodLabel = $row->method === 'visa' ? 'VISA' : ($row->method === 'crypto' ? 'Crypto' : 'Momo/OM');
                                $tone = $row->status === 'paid' ? 'ok' : ($row->status === 'failed' ? 'no' : 'wait');
                                $statusLabel = $row->status === 'paid' ? 'Paid' : ($row->status === 'failed' ? 'Not paid' : 'Pending');
                            @endphp
                            <tr>
                                <td><input type="checkbox" class="donations-line" name="ids[]" value="{{ $row->id }}"></td>
                                <td>{{ $row->created_at ? $row->created_at->format('M j H:i') : '' }}</td>
                                <td>{{ $row->person_name }}</td>
                                <td>{{ $row->phone }}</td>
                                <td>{{ number_format($row->amount, 0, '.', ' ') }} XAF</td>
                                <td>{{ $methodLabel }}</td>
                                <td>
                                    <span class="pay-pill pay-pill-{{ $tone }}">{{ $statusLabel }}</span>
                                    @if($row->status === 'failed' && $row->error)
                                        <div class="pay-note">{{ $row->error }}</div>
                                    @endif
                                </td>
                                <td>{{ $row->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="pay-muted">No mobile payments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(count($rows))
                <div class="pay-actions">
                    <button class="pay-go" type="submit" id="donationsDelete" style="background:#fdecec;color:#9b1c1c">Delete selected</button>
                </div>
            @endif
        </form>
    </div>
</section>
<script>
(function () {
    var form = document.getElementById('donationsForm');
    if (!form) return;
    var all = document.getElementById('donationsAll');
    var del = document.getElementById('donationsDelete');
    function boxes() { return form.querySelectorAll('.donations-line'); }
    if (all) {
        all.addEventListener('change', function () {
            boxes().forEach(function (box) { box.checked = all.checked; });
        });
    }
    if (del) {
        del.addEventListener('click', function (event) {
            var count = 0;
            boxes().forEach(function (box) { if (box.checked) count++; });
            if (!count) { event.preventDefault(); alert('Select the payments you want to delete.'); return; }
            if (!confirm('Delete ' + count + (count === 1 ? ' payment?' : ' payments?'))) { event.preventDefault(); }
        });
    }
})();
</script>
@if($pendingTotal > 0)
<script>
setTimeout(function () { window.location.reload(); }, 15000);
</script>
@endif
@endsection

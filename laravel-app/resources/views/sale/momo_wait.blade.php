@extends('layout.main')
@section('content')
@include('payout.partials.style')
@php
    $clientName = $sale->customer ? $sale->customer->name : 'the customer';
    $shownAmount = 0;
    if (! empty($lines)) {
        foreach ($lines as $line) {
            $shownAmount += (float) $line['amount'];
        }
    } else {
        $shownAmount = (float) $amount;
    }
@endphp
<style>
    .momo-live { display: flex; align-items: center; gap: 10px; }
    .momo-dot {
        width: 12px; height: 12px; border-radius: 50%; background: #c6ab47;
        box-shadow: 0 0 0 0 rgba(198, 171, 71, .7);
        animation: momo-pulse 1.6s ease-out infinite;
    }
    .momo-screen.is-done .momo-dot { animation: none; box-shadow: none; background: #9b1c1c; }
    .momo-screen.is-paid .momo-dot { animation: none; box-shadow: none; background: #146c43; }
    .momo-title { margin: 0 0 6px; font-size: 22px; font-weight: 800; color: #9b1c1c; letter-spacing: -.02em; }
    .momo-hint { margin: 0; font-size: 16px; line-height: 1.45; color: #1f2a44; }
    .momo-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .momo-actions .pay-open, .momo-actions .pay-danger { min-height: 48px; padding: 0 22px; font-size: 15px; }
    @keyframes momo-pulse {
        70% { box-shadow: 0 0 0 12px rgba(198, 171, 71, 0); }
        100% { box-shadow: 0 0 0 0 rgba(198, 171, 71, 0); }
    }
</style>
<section class="container-fluid pay-app momo-screen" id="momo-screen">
    <div class="pay-balance">
        <div>
            <div class="pay-kicker">MoMo / OM</div>
            <div class="pay-total">{{ number_format($shownAmount, 0, '.', ' ') }} XAF</div>
            <div class="pay-sub">Ask {{ $clientName }} to approve this payment on their phone.</div>
        </div>
        <div class="pay-chips">
            @if(empty($lines))
                <span class="pay-chip">Number <strong>{{ $phone }}</strong></span>
            @endif
            <span class="pay-chip">Sale <strong>{{ $sale->reference_no }}</strong></span>
        </div>
    </div>

    <div class="pay-card">
        <div class="pay-card-head">
            <h2>Phone approval</h2>
            <div class="momo-live">
                <span class="momo-dot" aria-hidden="true"></span>
                <span id="momo-pill" class="pay-pill pay-pill-wait">Waiting</span>
            </div>
        </div>
        <div class="pay-card-body">
            @if(! empty($lines))
                <table class="pay-table" style="margin-bottom:14px">
                    <thead><tr><th>Number</th><th>Amount</th></tr></thead>
                    <tbody>
                    @foreach($lines as $line)
                        <tr>
                            <td>{{ $line['phone'] }}</td>
                            <td>{{ number_format($line['amount'], 0, '.', ' ') }} XAF</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            <h3 id="momo-title" class="momo-title" style="display:none">Insufficient fund</h3>
            <p id="momo-hint" class="momo-hint">Waiting for approval. This screen stays here until the phone answers.</p>
            <div id="momo-actions" class="momo-actions" style="display:none">
                <a class="pay-open" href="{{ url('pos') }}">Back to POS</a>
                <form method="POST" action="{{ $cancelUrl }}" onsubmit="return confirm('Cancel this transaction? The sale will be removed.')">
                    @csrf
                    <button type="submit" class="pay-danger">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
@section('scripts')
<script>
(function () {
    var screen = document.getElementById('momo-screen');
    var hint = document.getElementById('momo-hint');
    var title = document.getElementById('momo-title');
    var actions = document.getElementById('momo-actions');
    var pill = document.getElementById('momo-pill');
    var started = Date.now();
    var stopped = false;
    function setPill(label, tone) {
        pill.textContent = label;
        pill.className = 'pay-pill pay-pill-' + tone;
    }
    function showResult(message) {
        stopped = true;
        screen.classList.add('is-done');
        var text = message || 'The payment was not approved. The sale is in Awaiting Payment.';
        if (text.indexOf('Insufficient fund') === 0) {
            title.style.display = '';
            title.textContent = 'Insufficient fund';
            hint.textContent = 'The client does not have enough fund.';
            setPill('Insufficient fund', 'no');
        } else {
            title.style.display = 'none';
            hint.textContent = text;
            setPill('Not approved', 'no');
        }
        actions.style.display = '';
    }
    function tick() {
        if (stopped) {
            return;
        }
        fetch(@json($statusUrl), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'paid' && data.url) {
                    screen.classList.remove('is-done');
                    screen.classList.add('is-paid');
                    setPill('Paid', 'ok');
                    hint.textContent = 'Payment received. Opening the invoice.';
                    window.location = data.url;
                    return;
                }
                if (data.status === 'failed') {
                    showResult(data.message);
                    return;
                }
                if (Date.now() - started > 180000) {
                    showResult('Still waiting. The sale is in Awaiting Payment. Go back to POS, or cancel this transaction.');
                    return;
                }
                window.setTimeout(tick, 4000);
            })
            .catch(function () { window.setTimeout(tick, 4000); });
    }
    var opening = @json($failedMessage);
    if (opening) {
        showResult(opening);
        return;
    }
    tick();
})();
</script>
@endsection

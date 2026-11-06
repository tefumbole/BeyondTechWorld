@extends('layout.main')
@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">MOMO/OM</h4>
                    </div>
                    <div class="card-body">
                        <p class="mb-1">Ask <strong>{{ $sale->customer ? $sale->customer->name : 'the customer' }}</strong> to approve this payment on their phone.</p>
                        @if(! empty($lines))
                            <table class="table">
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
                        @else
                        <p class="mb-1">Number: <strong>{{ $phone }}</strong></p>
                        <p class="mb-3">Amount: <strong>{{ number_format($amount, 0, '.', ' ') }} XAF</strong></p>
                        @endif
                        <h5 id="momo-title" class="mb-2" style="display:none">Insufficient fund</h5>
                        <p id="momo-hint">Waiting for approval. This screen stays here until the phone answers.</p>
                        <div id="momo-actions" class="mt-3" style="display:none">
                            <a class="btn btn-secondary" href="{{ url('pos') }}">Back to POS</a>
                            <form method="POST" action="{{ $cancelUrl }}" style="display:inline-block;margin-left:8px" onsubmit="return confirm('Cancel this transaction? The sale will be removed.')">
                                @csrf
                                <button type="submit" class="btn btn-danger">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
@section('scripts')
<script>
(function () {
    var hint = document.getElementById('momo-hint');
    var title = document.getElementById('momo-title');
    var actions = document.getElementById('momo-actions');
    var started = Date.now();
    var stopped = false;
    function showResult(message) {
        stopped = true;
        var text = message || 'The payment was not approved. The sale is in Awaiting Payment.';
        if (text.indexOf('Insufficient fund') === 0) {
            title.style.display = '';
            title.textContent = 'Insufficient fund';
            hint.textContent = 'The client does not have enough fund.';
        } else {
            title.style.display = 'none';
            hint.textContent = text;
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

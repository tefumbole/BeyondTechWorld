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
                        <p class="mb-1">Number: <strong>{{ $phone }}</strong></p>
                        <p class="mb-3">Amount: <strong>{{ number_format($amount, 0, '.', ' ') }} XAF</strong></p>
                        <p id="momo-hint">Waiting for approval. This screen stays here until the phone answers.</p>
                        <a id="momo-back" class="btn btn-secondary" href="{{ url('pos') }}" style="display:none">Back to POS</a>
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
    var back = document.getElementById('momo-back');
    var started = Date.now();
    function tick() {
        fetch(@json($statusUrl), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'paid' && data.url) {
                    hint.textContent = 'Payment received. Opening the invoice.';
                    window.location = data.url;
                    return;
                }
                if (data.status === 'failed') {
                    hint.textContent = data.message || 'The payment was not approved.';
                    back.style.display = '';
                    return;
                }
                if (Date.now() - started > 180000) {
                    hint.textContent = 'Still waiting. If the prompt expired, go back and send it again.';
                    back.style.display = '';
                    return;
                }
                window.setTimeout(tick, 4000);
            })
            .catch(function () { window.setTimeout(tick, 4000); });
    }
    tick();
})();
</script>
@endsection

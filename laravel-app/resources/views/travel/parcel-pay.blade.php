@extends('travel.layout')
@section('content')
<style>
    .confirm{max-width:560px}
    .who{background:#f7f8f2;border-radius:16px;padding:16px;margin:12px 0}
    .who b{font-size:22px}
    .lookup{display:flex;gap:8px;align-items:end}
    .lookup label{flex:1}
</style>
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Confirm MoMo</h1>
    <p>{{ $parcel->tracking_ref }} · {{ $origin }} to {{ $destination }} · {{ \App\Travel\TravelCatalog::money($parcel->fee_xaf) }}</p>
</section>
<section class="panel confirm">
    @if($parcel->payment_status === 'paid')
        <p class="notice">This payment is already confirmed. The invoice has been sent.</p>
    @else
        <p>Check the number that will approve the payment. The name must match the Mobile Money account before you continue.</p>
        <form method="post" action="{{ route('travel.parcels.charge', $parcel->id) }}" id="momo-form">
            @csrf
            <div class="lookup">
                <label>Paying number
                    <input name="payer_phone" type="tel" required value="{{ old('payer_phone', $parcel->sender_phone) }}">
                </label>
                <button type="button" class="primary" id="find-payer">Find name</button>
            </div>
            <div class="who" id="payer-box" hidden>
                <span class="muted" id="payer-copy"></span>
                <label>Confirm account name
                    <input name="account_name" required value="{{ old('account_name') }}">
                </label>
            </div>
            @if($sandbox)
                <p class="muted">Sandbox mode does not send a MoMo prompt. Confirming here records the payment and sends both messages.</p>
                <button class="primary">Confirm sandbox payment</button>
            @else
                <button class="primary">Send MoMo prompt</button>
            @endif
        </form>
    @endif
</section>
<script>
(function () {
    var form = document.getElementById('momo-form');
    if (!form) return;
    var box = document.getElementById('payer-box');
    document.getElementById('find-payer').addEventListener('click', function () {
        var button = this;
        button.disabled = true;
        fetch('{{ route('travel.parcels.lookup') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name=_token]').value
            },
            body: JSON.stringify({phone: form.payer_phone.value})
        }).then(function (response) {
            return response.json().then(function (body) { return {ok: response.ok, body: body}; });
        }).then(function (result) {
            box.hidden = false;
            document.getElementById('payer-copy').textContent = result.body.message || 'Type the name on the account.';
            if (result.ok && result.body.phone) form.payer_phone.value = result.body.phone;
            if (result.ok && result.body.name) form.account_name.value = result.body.name;
        }).catch(function () {
            box.hidden = false;
            document.getElementById('payer-copy').textContent = 'The name could not be read. Type the name on the account.';
        }).then(function () { button.disabled = false; });
    });
    form.addEventListener('submit', function (e) {
        if (box.hidden || !form.account_name.value.trim()) e.preventDefault();
    });
})();
</script>
@endsection

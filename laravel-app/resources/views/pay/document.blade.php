<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay {{ $link->reference_no }}</title>
    <style>
        :root { --primary:#0b3f90; --accent:#c6ab47; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background:#f3f6fb; color:#1f2a44; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 28px 16px 48px; }
        .card { background:#fff; border:1px solid #e3e9f4; border-radius:16px; padding:22px; box-shadow:0 8px 24px rgba(11,63,144,.06); }
        h1 { margin:0 0 6px; font-size:22px; color:var(--primary); }
        .muted { color:#6b7894; }
        .amount { margin:18px 0; font-size:32px; font-weight:750; letter-spacing:-.03em; }
        .row { margin:8px 0; }
        .go, .visa {
            display:block; width:100%; margin-top:12px; border:0; border-radius:999px; padding:14px 16px;
            font-weight:700; font-size:16px; cursor:pointer; text-align:center; text-decoration:none;
        }
        .go { background:var(--primary); color:#fff; }
        .visa { background:#fff; color:var(--primary); border:1px solid #c9d6ee; }
        .alert { margin-bottom:12px; padding:12px 14px; border-radius:12px; }
        .ok { background:#e8f7ee; color:#146c36; }
        .no { background:#fdecec; color:#9b1c1c; }
        .wait { background:#fff7e6; color:#8a5a00; }
    </style>
</head>
<body>
<div class="wrap">
    @if(session('not_permitted'))<div class="alert no">{{ session('not_permitted') }}</div>@endif
    <div class="card">
        <h1>{{ $link->kind === 'sale' ? 'Invoice' : 'Quotation' }} {{ $link->reference_no }}</h1>
        <div class="muted">Dear {{ $link->person_name }},</div>
        @if($link->requested_by)
            <div class="row muted">Requested by {{ $link->requested_by }}</div>
        @endif
        <div class="amount">{{ number_format($link->amount, 0, '.', ' ') }} XAF</div>
        @if($link->status === 'paid')
            <div class="alert ok">This payment has been received.</div>
        @else
            @if($link->status === 'pending' && $link->method === 'momo')
                <div class="alert wait" id="waitNote">Approve the prompt on your phone. MTN and Orange both use this step.</div>
            @elseif($link->status === 'failed')
                <div class="alert no">{{ $link->error ?: 'The payment was not approved.' }}</div>
            @endif
            <form method="POST" action="{{ route('document.pay.submit', ['token' => $link->token]) }}">
                @csrf
                <button class="go" type="submit" name="method" value="momo">Pay with MTN or Orange</button>
                <button class="visa" type="submit" name="method" value="visa">Pay with VISA</button>
            </form>
            @if($link->payment_link && $link->method === 'visa' && $link->status !== 'paid')
                <a class="visa" href="{{ $link->payment_link }}" style="margin-top:12px">Open the VISA page</a>
            @endif
        @endif
    </div>
</div>
@if($link->status === 'pending')
<script>
(function () {
    var url = @json(route('document.pay.status', ['token' => $link->token]));
    function tick() {
        fetch(url, {headers: {'Accept': 'application/json'}}).then(function (res) { return res.json(); }).then(function (body) {
            if (body.status === 'paid' || body.status === 'failed') {
                window.location.reload();
            }
        }).catch(function () {});
    }
    setInterval(tick, 4000);
})();
</script>
@endif
</body>
</html>

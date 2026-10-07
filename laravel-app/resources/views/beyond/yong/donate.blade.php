<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donate — {{ $invitation->name }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: "Source Sans Pro", sans-serif; background: #071433; color: #fff; }
        .card { width: min(420px, calc(100% - 28px)); background: #fffaf1; color: #1c160e; border-radius: 18px; padding: 22px; text-align: center; }
        h1 { color: #0b245c; font-size: 24px; margin: 0 0 8px; }
        a.pay { display: block; margin-top: 10px; background: #0b245c; color: #fff; text-decoration: none; border-radius: 999px; padding: 12px; font-weight: 700; }
        a.gold { background: #d4af37; color: #1c160e; }
        .err { color: #991b1b; }
    </style>
</head>
<body>
<div class="card">
    <h1>Donate {{ $invitation->pledgeLabel() }}</h1>
    <p>{{ $invitation->name }} · {{ $invitation->typeLabel() }} invitation</p>
    @if($invitation->payment_status === 'paid')
        <p>This pledge is already paid. Thank you.</p>
    @else
        @if($pay === 'failed' || session('pay_error'))
            <p class="err">{{ session('pay_error') ?: 'Payment did not finish. You can try again.' }}</p>
        @endif
        <a class="pay" href="{{ url('/yong/donate/'.$invitation->id.'/start?method=momo') }}">Mobile money</a>
        <a class="pay gold" href="{{ url('/yong/donate/'.$invitation->id.'/start?method=visa') }}">Card</a>
    @endif
</div>
</body>
</html>

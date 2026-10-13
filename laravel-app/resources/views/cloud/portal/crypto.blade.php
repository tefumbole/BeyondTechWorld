@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <p class="stage-kicker">Crypto payment</p>
    <h1>Send USDT</h1>
    <p>Send exactly <strong>{{ $crypto['usdt'] ?? '' }} USDT</strong> on {{ $crypto['network'] ?? 'Tron (TRC20)' }} to this address:</p>
    <p style="word-break:break-all;font-weight:700">{{ $crypto['address'] ?? '' }}</p>
    <p class="muted">The amount includes a small unique part so this payment can be matched to your subscription of {{ number_format((float) $payment->amount, 0) }} {{ $payment->currency }}.</p>
    <p><a class="btn" href="{{ route('cloud.pay.return', ['payment' => $payment->id]) }}">I have sent it</a></p>
</div>
@endsection

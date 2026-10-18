@extends('gbhs.public.layout')
@section('content')
<h1>Donation</h1>
<div class="card">
    <p>{{ number_format($payment->amount) }} XAF · {{ $payment->method }}</p>
    <p>Status: <strong>{{ $status }}</strong></p>
    @if($status === 'failed')
        <p>{{ $payment->error ?: 'The payment was not completed.' }}</p>
        <a class="btn" href="{{ route('gbhs.donate') }}">Try again</a>
    @endif
    @if(!empty($crypto))
        <p>Bitcoin on this site is paid as <strong>{{ $crypto['usdt'] }} {{ $crypto['coin'] }}</strong> on {{ $crypto['network'] }}.</p>
        <p style="word-break:break-all">{{ $crypto['address'] }}</p>
    @endif
    @if($status === 'pending' || $status === 'awaiting_transfer')
        <p>This page does not mark the gift as received. It is recorded after Momo, VISA, or Bitcoin confirms the payment.</p>
        <a class="btn" href="{{ route('gbhs.pay.return', $payment->public_token) }}">Check again</a>
    @endif
</div>
@endsection

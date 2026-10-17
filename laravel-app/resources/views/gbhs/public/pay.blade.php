@extends('gbhs.public.layout')
@section('content')
<h1>Donation</h1>
<div class="card">
    <p>{{ number_format($payment->amount) }} XAF · {{ $payment->method }}</p>
    <p>Status: <strong>{{ $status }}</strong></p>
    @if($status === 'pending' || $status === 'awaiting_transfer')
        <p>This page does not mark the gift as received. Refresh after you approve the prompt, or wait for the association to record a bank transfer.</p>
        <a class="btn" href="{{ route('gbhs.pay.return', $payment->public_token) }}">Check again</a>
    @endif
</div>
@endsection

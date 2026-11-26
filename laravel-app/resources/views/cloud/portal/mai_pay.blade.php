@extends('beyond.layout')

@section('title', 'Whats MAI payment')

@section('content')
<section class="py-10" style="background:#041833;">
    <div class="max-w-xl mx-auto px-4">
        <div style="max-width:460px;margin:0 auto;background:#07244f;color:#fff;border:1px solid rgba(212,175,55,.65);border-radius:28px;padding:1.4rem;">
            <h1 style="margin:0 0 0.4rem;">Approve the payment</h1>
            <p>Approve the Mobile Money prompt on your phone, or finish the VISA page. This page checks the same payment record used by Payments.</p>
            <p style="color:#F5D76E;font-weight:800;">{{ number_format((float) $payment->amount, 0) }} {{ $payment->currency }}</p>
            <p><a href="{{ route('whatsapp.mai.pay', ['payment' => $payment->id]) }}" style="color:#F5D76E;">Check again</a></p>
        </div>
    </div>
</section>
<meta http-equiv="refresh" content="5">
@endsection

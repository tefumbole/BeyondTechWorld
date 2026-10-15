@extends('beyond.layout')
@section('title', 'Connect')
@section('content')
<div style="max-width:32rem;margin:0 auto;padding:2rem 1rem">
    <h1>You are paid</h1>
    <p>{{ optional($package)->name }} until {{ $subscription->expires_at }}.</p>
    @if($link)
        <form id="hs-login" method="POST" action="{{ $link }}">
            <input type="hidden" name="username" value="{{ $subscription->username }}">
            <input type="hidden" name="password" value="{{ $password }}">
            <input type="hidden" name="dst" value="{{ $dst }}">
            <input type="hidden" name="popup" value="true">
            @if($chapId !== '')
                <input type="hidden" name="chap-id" value="{{ $chapId }}">
                <input type="hidden" name="chap-challenge" value="{{ $chapChallenge }}">
            @endif
            <button type="submit" style="width:100%;border:0;border-radius:999px;padding:.8rem 1rem;background:#0b3f90;color:#fff;font-weight:700">Connect</button>
        </form>
        <script>setTimeout(function () { document.getElementById('hs-login').submit(); }, 600);</script>
    @endif
    <p>Username</p>
    <p style="font-size:1.4rem"><strong>{{ $subscription->username }}</strong></p>
    <p>Password</p>
    <p style="font-size:1.4rem"><strong>{{ $subscription->access_secret }}</strong></p>
    <p>The same details are on WhatsApp. <a href="https://wa.me/{{ $support }}">Support</a></p>
</div>
@endsection

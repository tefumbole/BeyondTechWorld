@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Messaging</h1>
    <p>{{ $tenant->system_name }}</p>
    <p><strong>Messaging subscription:</strong> {{ !empty($entitled) ? 'Trial active' : 'Not active' }}</p>
    @if($connected)
        <p><strong>WhatsApp:</strong> Connected for this company.</p>
    @else
        <p>A messaging trial does not include a WhatsApp number.</p>
        <p><strong>WhatsApp:</strong> Not connected</p>
        <p><strong>Setup:</strong> Admin setup required. This page does not use another company's session or API credentials.</p>
    @endif
    <p><strong>SMS: Not available</strong></p>
    <p>SMS is coming later. It is not part of company setup, and no SMS provider account is required.</p>
    <p class="muted">{{ $paymentNotice }}</p>
</div>
@endsection

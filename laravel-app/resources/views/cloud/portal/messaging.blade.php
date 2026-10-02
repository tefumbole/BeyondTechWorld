@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Messaging</h1>
    <p>{{ $tenant->system_name }}</p>
    <p>Messaging: {{ !empty($entitled) ? 'Active' : 'Not active' }}</p>
    @if($connected)
        <p>WhatsApp: Connected for this company.</p>
    @else
        <p>A messaging trial can be active while WhatsApp is not connected.</p>
        <p><strong>WhatsApp: Not connected</strong></p>
        <p>Admin setup required. This page does not use another company's session or API credentials.</p>
    @endif
    <p><strong>SMS: Not available</strong></p>
    <p>SMS is coming later. It is not part of company setup, and no SMS provider account is required.</p>
    <p class="muted">{{ $paymentNotice }}</p>
</div>
@endsection

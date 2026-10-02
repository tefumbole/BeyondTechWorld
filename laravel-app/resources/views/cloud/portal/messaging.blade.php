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
    @if(config('cloud.whatsapp_self_connect') && config('cloud.whatsapp.provisioning_enabled'))
        @php $wa = \Illuminate\Support\Facades\Schema::hasTable('cloud_whatsapp_connections') ? \App\Cloud\CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->orderBy('id', 'desc')->first() : null; @endphp
        <div class="card">
            <p><strong>Connection status:</strong> {{ $wa ? $wa->status : 'NOT_CONFIGURED' }}</p>
            <p><strong>Phone:</strong> {{ $wa && $wa->phone_number ? $wa->phone_number : 'Not confirmed' }}</p>
            <p><strong>Provider:</strong> {{ $wa ? $wa->provider : 'Not set' }}</p>
            <p><strong>Last check:</strong> {{ $wa && $wa->last_health_check_at ? $wa->last_health_check_at : 'None' }}</p>
            @if(!empty($entitled))
                <form method="POST" action="{{ route('cloud.whatsapp.connect') }}">@csrf<button type="submit">Connect WhatsApp</button></form>
            @endif
            @if($wa && $wa->status === 'AWAITING_QR')
                <p><a href="{{ route('cloud.whatsapp.qr', $wa->id) }}">Show code</a></p>
                <form method="POST" action="{{ route('cloud.whatsapp.refresh', $wa->id) }}">@csrf<button type="submit">Check connection</button></form>
            @endif
            @if($wa && in_array($wa->status, ['CONNECTED', 'ACTIVE'], true))
                <form method="POST" action="{{ route('cloud.whatsapp.disconnect', $wa->id) }}">@csrf<input type="hidden" name="confirm" value="DISCONNECT"><button type="submit">Disconnect WhatsApp</button></form>
            @endif
        </div>
    @endif
    <p><strong>SMS: Not available</strong></p>
    <p>SMS is coming later. It is not part of company setup, and no SMS provider account is required.</p>
</div>
@endsection

@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <p style="text-align:right;margin:0 0 0.4rem;"><a href="{{ route('whatsapp.mai') }}">Close</a></p>
    <h1>Messaging</h1>
    <p>{{ $tenant->system_name }}</p>
    <p><strong>Renewal:</strong> {{ !empty($entitled) ? 'Active. Sending and MAI are available.' : 'Not active. The inbox stays readable. Sending and MAI stay off until this is paid.' }}</p>
    @php $wa = \Illuminate\Support\Facades\Schema::hasTable('cloud_whatsapp_connections') ? \App\Cloud\CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->orderBy('id', 'desc')->first() : null; @endphp
    @php $waOn = $wa && in_array($wa->status, ['CONNECTED', 'ACTIVE'], true); @endphp
    <p><strong>WhatsApp:</strong> {{ $waOn ? 'Connected' : 'Not connected' }}</p>
    <p>A verification code only signs you in. It does not connect WhatsApp. Old chats from the phone are not imported.</p>
    @if(config('cloud.whatsapp_self_connect') && config('cloud.whatsapp.provisioning_enabled'))
        <div class="card">
            <h2>Connect your WhatsApp</h2>
            <p>On the phone open WhatsApp, tap Linked devices, then Link a device, and scan the QR code. The linked number must be {{ $tenant->phone ?: 'the number you entered' }}.</p>
            <p><strong>Connection status:</strong> {{ $wa ? $wa->status : 'NOT_CONFIGURED' }}</p>
            <p><strong>Phone:</strong> {{ $wa && $wa->phone_number ? $wa->phone_number : 'Not confirmed' }}</p>
            @if(!empty($entitled) && ! $waOn)
                <form method="POST" action="{{ route('cloud.whatsapp.connect') }}">
                    @csrf
                    <button type="submit" name="link" value="qr">Show QR code</button>
                </form>
            @endif
            @if($wa && $wa->status === 'AWAITING_QR')
                <p>On the phone open WhatsApp, tap Linked devices, then Link a device, and scan this code.</p>
                <p><img src="{{ route('cloud.whatsapp.qr', $wa->id) }}?image=1" alt="WhatsApp QR code" width="280" height="280"></p>
                <form method="POST" action="{{ route('cloud.whatsapp.refresh', $wa->id) }}">@csrf<button type="submit">Check connection</button></form>
            @endif
            @if($waOn)
                <p><a href="{{ route('whatsapp.chats') }}">Open inbox</a></p>
                <form method="POST" action="{{ route('cloud.whatsapp.disconnect', $wa->id) }}">@csrf<input type="hidden" name="confirm" value="DISCONNECT"><button type="submit">Disconnect WhatsApp</button></form>
            @endif
            @if(!empty($entitled))
                <p>Signing out of Mbole AI leaves this WhatsApp connection in place.</p>
            @endif
        </div>
    @else
        <p><strong>Setup:</strong> Admin setup required. WhatsApp linking is not switched on for customer companies yet. This page does not use the staff session.</p>
    @endif
    <p><strong>SMS: Not available</strong></p>
    <p>SMS is coming later. It is not part of company setup, and no SMS provider account is required.</p>
</div>
@endsection

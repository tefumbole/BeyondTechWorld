@extends('whatsapp_hub.customer_shell')

@section('content')
<main style="max-width:520px;margin:0 auto;padding:24px 16px 48px;font-family:Georgia,serif;color:#10233f;">
    <p style="letter-spacing:.04em;color:#0b3f90;font-weight:700;">WHATS MAI</p>
    <h1 style="margin:0 0 8px;">Your dashboard</h1>
    <p>{{ $user->name }}</p>
    <p>WhatsApp {{ $phone !== '' ? '+'.$phone : 'not linked yet' }}</p>
    <p>Status {{ $status }}</p>
    <p><a href="{{ route('whatsapp.mai.inbox') }}">Open inbox</a></p>
    @if($status !== 'CONNECTED')
        <p><a href="{{ route('whatsapp.mai.link') }}">Link WhatsApp</a></p>
    @endif
</main>
@endsection

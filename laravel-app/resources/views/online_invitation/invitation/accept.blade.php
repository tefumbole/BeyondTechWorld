@extends('layout.invitation')
@section('content')

<style>
    * { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
    body { color: var(--oi-font, #f3e7c1); }

    .page-bg {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 0;
        background-repeat: no-repeat;
        background-position: center center;
        background-size: cover;
    }
    .overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 1;
        background: rgba(0,0,0,0.42);
    }

    .toolbar {
        position: fixed;
        top: 12px;
        right: 12px;
        z-index: 5;
        display: flex;
        gap: 8px;
    }

    .frame {
        position: relative;
        z-index: 2;
        padding: 18px;
        max-width: 920px;
        margin: 0 auto;
    }

    .border-outer {
        border: 3px solid var(--oi-border, #c8a75e);
        padding: 8px;
    }
    .border-inner {
        border: 1px solid var(--oi-border, #c8a75e);
        padding: 26px 24px 20px 24px;
        min-height: calc(100vh - 120px);
        color: var(--oi-font, #f3e7c1);
    }
    .border-inner * { color: inherit !important; }

    .title {
        text-align: center;
        letter-spacing: 3px;
        font-size: 36px;
        font-weight: 700;
        margin: 4px 0 2px 0;
        text-transform: uppercase;
        color: var(--oi-font, #f3e7c1);
    }
    .subtitle {
        text-align: center;
        letter-spacing: 4px;
        font-size: 13px;
        font-weight: 600;
        color: inherit;
        margin: 0 0 18px 0;
        text-transform: uppercase;
        opacity: 0.9;
    }

    .dear {
        text-align: center;
        font-size: 22px;
        font-style: italic;
        margin: 8px 0 14px 0;
        color: inherit;
    }
    .invite-line {
        text-align: center;
        font-size: 16px;
        font-weight: 500;
        color: inherit;
        margin: 0 0 8px 0;
        line-height: 1.35;
    }
    .event-name {
        text-align: center;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        line-height: 1.15;
        margin: 0 0 14px 0;
        border-bottom: 2px solid var(--oi-border, #c8a75e);
        padding-bottom: 12px;
    }
    .optional-message {
        text-align: center;
        font-size: 14px;
        margin: 0 0 16px 0;
        opacity: 0.95;
    }

    .details {
        margin: 8px auto 0 auto;
        width: 92%;
        border: 2px solid var(--oi-border, #c8a75e);
        border-radius: 6px;
        padding: 16px 18px;
        background: rgba(0,0,0,0.22);
        color: inherit;
    }
    .detail-row { text-align: center; padding: 8px 0; }
    .detail-row + .detail-row {
        border-top: 1px solid var(--oi-border, #c8a75e);
        margin-top: 6px;
        padding-top: 14px;
    }
    .detail-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        opacity: 0.85;
        margin-bottom: 4px;
    }
    .detail-value {
        display: block;
        font-size: 22px;
        font-weight: 800;
        line-height: 1.25;
    }

    .rsvp {
        text-align: center;
        margin-top: 16px;
        color: inherit;
    }
    .rsvp .label {
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 3px;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .rsvp .url {
        font-size: 20px;
        font-weight: 800;
        word-break: break-word;
        line-height: 1.3;
    }

    .qr-box {
        width: 168px;
        height: 168px;
        background: #fff;
        border: 3px solid var(--oi-border, #c8a75e);
        padding: 8px;
        box-sizing: border-box;
        margin: 22px auto 0 auto;
    }
    .qr-box img { width: 100%; height: 100%; }

    .footer-note {
        margin: 18px auto 0 auto;
        text-align: center;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.2px;
        line-height: 1.4;
        border-top: 1px solid var(--oi-border, #c8a75e);
        padding-top: 12px;
        max-width: 520px;
        color: inherit;
    }

    @media (max-width: 575.98px) {
        .frame { padding: 12px; }
        .border-inner { padding: 16px; min-height: auto; }
        .title { font-size: 34px; }
        .dear { font-size: 22px; }
        .details { width: 100%; }
    }
    @media print {
        .d-print-none { display: none !important; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #000 !important; }
        .frame { max-width: none; padding: 0; }
        .border-inner { min-height: 740px; }
    }
</style>

@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('not_permitted') }}
    </div>
@endif
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {!! session()->get('message') !!}
    </div>
@endif

@php
    $event = $data->event;
    $template = $event ? $event->template : null;
    $bg = $template ? (string) ($template->background ?: '') : '';
    $bg = trim($bg) ?: '#111';

    // Normalize stored background so it works when app is served from a subdirectory (e.g. /public).
    // If background is a local absolute path like /images/..., convert it to asset() URL.
    $bgCss = $bg;
    $bgRef = $bg;
    if (preg_match('/url\\(([^)]+)\\)/i', $bg, $m)) {
        $bgRef = trim($m[1], " \t\n\r\0\x0B'\"");
    }
    if (preg_match('#^https?://#i', $bgRef)) {
        $bgCss = "url('" . \App\Support\OnlineInvitationUrl::ensurePublicInAppUrl($bgRef) . "')";
    } elseif (preg_match('#^/(public/)?images/#i', $bgRef)) {
        $bgCss = "url('" . \App\Support\OnlineInvitationUrl::ensurePublicInAppUrl(asset(ltrim($bgRef, '/'))) . "')";
    }

    $acceptUrl = route('online_invitation.invite.show', $data->token);

    $recipientName = $data->recipient_name ?: (optional($data->customer)->name ?: optional($data->user)->name);
    $recipientEmail = $data->recipient_email ?: (optional($data->customer)->email ?: optional($data->user)->email);
    $recipientEmail = trim((string) $recipientEmail);
    $recipientEmailLower = strtolower($recipientEmail);
    if ($recipientEmail === '' || in_array($recipientEmailLower, ['—', '-', 'n/a', 'na', 'null', 'none'], true)) {
        $recipientEmail = null;
    }
    $recipientPhone = $data->recipient_phone ?: (optional($data->customer)->phone_number ?: optional($data->user)->phone);

    $categoryName = $data->category ? $data->category->name : null;
    $optionalMessage = $data->message ?: null;
    $invitationCategoryTitle = trim((string) ($categoryName ?? '')) !== '' ? $categoryName : 'Invitation';

    $eventAtText = $event ? $event->event_at : null;
    try {
        if ($event && $event->event_at) {
            $eventAtText = \Carbon\Carbon::parse($event->event_at)->format('D, M d, Y h:i A');
        }
    } catch (\Throwable $e) {
        $eventAtText = $event ? $event->event_at : null;
    }

    $borderColor = trim((string) ($data->border_color ?? '')) ?: '#c8a75e';
    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $borderColor)) {
        $borderColor = '#c8a75e';
    }
    $fontColor = trim((string) ($data->font_color ?? '')) ?: '#f3e7c1';
    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $fontColor)) {
        $fontColor = '#f3e7c1';
    }

    $rsvpValue = trim((string) ($data->rsvp ?? ''));
    $rsvpValue = $rsvpValue !== '' ? $rsvpValue : $acceptUrl;
    $rsvpLabel = trim((string) ($data->rsvp ?? '')) !== '' ? 'RSVP' : 'RSVP / View';

    $qrDataUri = \App\Support\OnlineInvitationQr::dataUri($acceptUrl, 320, 1);
@endphp

<style>
    :root {
        --oi-border: {{ $borderColor }};
        --oi-font: {{ $fontColor }};
    }
</style>

<div class="toolbar d-print-none">
    <a class="btn btn-sm btn-outline-light" href="{{ route('online_invitation.invite.png', $data->token) }}">PNG</a>
    <a class="btn btn-sm btn-outline-light" href="{{ route('online_invitation.invite.pdf', $data->token) }}">PDF</a>
    <button type="button" class="btn btn-sm btn-outline-light" onclick="window.print()">Print</button>
</div>

<div class="page-bg" style="background: {{ $bgCss }};"></div>
<div class="overlay"></div>

<div class="frame">
    <div class="border-outer">
        <div class="border-inner">
            <div class="title">{{ $invitationCategoryTitle }}</div>
            <div class="subtitle">Invitation</div>

            <div class="dear">Dear {{ $recipientName ?: 'Guest' }},</div>
            <div class="invite-line">
                You are cordially invited as {{ $categoryName ?: 'Guest' }} to
            </div>
            <div class="event-name">{{ $event ? $event->name : 'Event' }}</div>
            @if($optionalMessage)
                <div class="optional-message">{{ $optionalMessage }}</div>
            @endif

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value">{{ $eventAtText ?: '—' }}</span>
                </div>
                @if($event && $event->location)
                    <div class="detail-row">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">{{ $event->location }}</span>
                    </div>
                @endif
            </div>

            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="QR Code">
            </div>

            <div class="footer-note">
                Please present this invitation at the venue.<br>
                This invitation is generated electronically.
            </div>

            <div class="rsvp">
                <div class="label">{{ $rsvpLabel }}</div>
                <div class="url">{{ $rsvpValue }}</div>
            </div>
        </div>
    </div>

    <div class="d-print-none" style="margin-top: 16px; text-align: center;">
        @if(!empty($canRsvp) && empty($data->used_at))
            <div style="margin-bottom: 12px;">
                <p class="text-light mb-2">Will you attend?</p>
                @if(($data->rsvp_status ?? 'pending') === 'accepted')
                    <span class="badge badge-success">You accepted — attending</span>
                @elseif(($data->rsvp_status ?? '') === 'declined')
                    <span class="badge badge-secondary">You declined</span>
                @else
                    <form action="{{ route('online_invitation.invite.rsvp_accept', $data->token) }}" method="POST" style="display:inline-block;margin:4px;">
                        @csrf
                        <button type="submit" class="btn btn-success">Accept invitation</button>
                    </form>
                    <form action="{{ route('online_invitation.invite.rsvp_decline', $data->token) }}" method="POST" style="display:inline-block;margin:4px;">
                        @csrf
                        <button type="submit" class="btn btn-outline-light">Decline</button>
                    </form>
                @endif
            </div>
        @endif

        @if(!empty($canManage))
            @if(!$data->used_at)
                <form action="{{ route('online_invitation.invite.accept_use', $data->token) }}" method="POST" onsubmit="return confirm('Admit this guest into the hall?')" style="display:inline-block;">
                    @csrf
                    <button type="submit" class="btn btn-primary">Admit guest</button>
                    <a class="btn btn-link text-light" href="{{ route('online_invitation.invitations.index') }}">Back</a>
                </form>
            @else
                <span class="badge badge-info">Already admitted</span>
                <a class="btn btn-link text-light" href="{{ route('online_invitation.invitations.index') }}">Back</a>
            @endif
        @endif
    </div>
</div>

@endsection

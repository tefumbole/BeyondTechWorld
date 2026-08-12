@extends('layout.invitation')
@section('content')

<style>
    * { font-family: "Palatino Linotype", "Book Antiqua", Palatino, DejaVu Serif, DejaVu Sans, sans-serif; }
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
        background: rgba(0,0,0,0.45);
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
        border: none;
        padding: 10px;
    }
    .border-inner {
        border: none;
        padding: 22px 22px 28px 22px;
        min-height: calc(100vh - 120px);
        color: var(--oi-font, #f3e7c1);
    }
    .border-inner * { color: inherit !important; }

    .title {
        text-align: center;
        letter-spacing: 2px;
        font-size: 44px;
        font-weight: 700;
        margin-top: 10px;
        margin-bottom: 4px;
        text-transform: uppercase;
        color: var(--oi-font, #f3e7c1);
    }
    .subtitle {
        text-align: center;
        letter-spacing: 2px;
        font-size: 18px;
        color: inherit;
        margin: 0 0 22px 0;
        text-transform: uppercase;
    }

    .dear {
        text-align: center;
        font-size: 28px;
        font-style: italic;
        margin: 10px 0 10px 0;
        color: inherit;
    }
    .message {
        text-align: center;
        font-size: 16px;
        color: inherit;
        margin: 0 0 18px 0;
    }

    .details {
        margin: 16px auto 0 auto;
        width: 86%;
        border-top: 1px solid var(--oi-border, #c8a75e);
        border-bottom: 1px solid var(--oi-border, #c8a75e);
        padding: 16px 0;
        color: inherit;
    }
    .details table { width: 100%; color: inherit; }
    .details td { padding: 6px 0; font-size: 15px; color: inherit; }
    .details .label { width: 120px; }
    .details .value { font-weight: 600; }

    .rsvp {
        text-align: center;
        margin-top: 18px;
        font-size: 13px;
        color: inherit;
    }
    .rsvp .url { font-size: 11px; word-break: break-all; }

    .qr-box {
        width: 190px;
        height: 190px;
        background: rgba(0,0,0,0.35);
        border: 2px solid var(--oi-border, #c8a75e);
        padding: 10px;
        box-sizing: border-box;
        margin: 18px auto 0 auto;
    }
    .qr-box img { width: 100%; height: 100%; }

    .footer-note {
        margin: 14px auto 0 auto;
        text-align: center;
        font-size: 13px;
        color: inherit;
        max-width: 520px;
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
    $rsvpLabel = trim((string) ($data->rsvp ?? '')) !== '' ? 'RSVP:' : 'RSVP / View:';

    $qrPng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(320)->margin(1)->generate($acceptUrl);
    $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);
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
            <div class="message">
                You are invited as a {{ $categoryName ?: 'Guest' }} to our {{ $event ? $event->name : 'Event' }}
            </div>
            @if($optionalMessage)
                <div class="message" style="font-size: 13px; margin-top: 6px;">
                    {{ $optionalMessage }}
                </div>
            @endif

            <div class="details">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="label">Date:</td>
                        <td class="value">{{ $eventAtText ?: '—' }}</td>
                    </tr>
                    @if($event && $event->location)
                        <tr>
                            <td class="label">Venue:</td>
                            <td class="value">{{ $event->location }}</td>
                        </tr>
                    @endif
                    @if($recipientPhone)
                        <tr>
                            <td class="label">Phone:</td>
                            <td class="value">{{ $recipientPhone }}</td>
                        </tr>
                    @endif
                    @if($recipientEmail)
                        <tr>
                            <td class="label">Email:</td>
                            <td class="value">{{ $recipientEmail }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            <div class="rsvp">
                {{ $rsvpLabel }}
                <div class="url">{{ $rsvpValue }}</div>
            </div>

            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="QR Code">
            </div>

            <div class="footer-note">
                Please present this invitation at the venue. This invitation is generated electronically.
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

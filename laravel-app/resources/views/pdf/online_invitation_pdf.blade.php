<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Invitation</title>
    @php
        $pdfBgColor = $pdfBgColor ?? '#ffffff';
        $pdfBgImage = $pdfBgImage ?? null;

        $borderColor = trim((string) ($borderColor ?? '#c8a75e')) ?: '#c8a75e';
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $borderColor)) {
            $borderColor = '#c8a75e';
        }

        $fontColor = trim((string) ($fontColor ?? '#f3e7c1')) ?: '#f3e7c1';
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $fontColor)) {
            $fontColor = '#f3e7c1';
        }

        $fontSize = (int) ($fontSize ?? 16);
        if ($fontSize < 12 || $fontSize > 28) {
            $fontSize = 16;
        }
        $fs = $fontSize / 16.0;
        $px = function ($base) use ($fs) {
            return (int) max(8, round($base * $fs));
        };

        $rsvpValue = !empty($rsvp) ? trim((string) $rsvp) : (string) ($acceptUrl ?? '');
        $rsvpValue = $rsvpValue !== '' ? $rsvpValue : (string) ($acceptUrl ?? '');
        $rsvpLabel = !empty($rsvp) ? 'RSVP' : 'RSVP / View';
        $invitationCategoryTitle = !empty($categoryName) ? $categoryName : 'Invitation';
        $inviteAs = !empty($categoryName) ? $categoryName : 'Guest';
        $eventTitle = !empty($eventName) ? $eventName : 'Event';
    @endphp
    <style type="text/css">
        * { font-family: "Helvetica Neue", Helvetica, Arial, DejaVu Sans, sans-serif; }
        @page { margin: 0; }
        body { margin: 0; color: {{ $fontColor }}; }

        .page-bg {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
        }

        .overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 1;
            background: rgba(0,0,0,0.42);
        }

        .frame {
            position: relative;
            z-index: 2;
            padding: 22px;
        }

        .border-outer {
            border: 3px solid {{ $borderColor }};
            padding: 8px;
        }
        .border-inner {
            border: 1px solid {{ $borderColor }};
            padding: 26px 24px 20px 24px;
            min-height: 760px;
            color: {{ $fontColor }};
        }
        .border-inner * { color: inherit; }

        .title {
            text-align: center;
            letter-spacing: 3px;
            font-size: {{ $px(36) }}px;
            font-weight: 700;
            margin: 4px 0 2px 0;
            text-transform: uppercase;
            color: {{ $fontColor }};
        }
        .subtitle {
            text-align: center;
            letter-spacing: 4px;
            font-size: {{ $px(13) }}px;
            font-weight: 600;
            color: inherit;
            margin: 0 0 18px 0;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .dear {
            text-align: center;
            font-size: {{ $px(22) }}px;
            font-style: italic;
            margin: 8px 0 14px 0;
            color: inherit;
        }

        .invite-line {
            text-align: center;
            font-size: {{ $px(16) }}px;
            font-weight: 500;
            color: inherit;
            margin: 0 0 8px 0;
            line-height: 1.35;
        }

        .event-name {
            text-align: center;
            font-size: {{ $px(34) }}px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 0 0 14px 0;
            color: {{ $fontColor }};
            border-bottom: 2px solid {{ $borderColor }};
            padding-bottom: 12px;
        }

        .optional-message {
            text-align: center;
            font-size: {{ $px(14) }}px;
            margin: 0 0 16px 0;
            color: inherit;
            opacity: 0.95;
        }

        .details {
            margin: 8px auto 0 auto;
            width: 92%;
            border: 2px solid {{ $borderColor }};
            border-radius: 6px;
            padding: 16px 18px;
            color: inherit;
            background: rgba(0,0,0,0.22);
        }
        .detail-row {
            text-align: center;
            padding: 8px 0;
        }
        .detail-row + .detail-row {
            border-top: 1px solid {{ $borderColor }};
            margin-top: 6px;
            padding-top: 14px;
        }
        .detail-label {
            display: block;
            font-size: {{ $px(12) }}px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .detail-value {
            display: block;
            font-size: {{ $px(22) }}px;
            font-weight: 800;
            letter-spacing: 0.3px;
            line-height: 1.25;
        }

        .qr-box {
            width: 168px;
            height: 168px;
            background: #fff;
            border: 3px solid {{ $borderColor }};
            padding: 8px;
            box-sizing: border-box;
            margin: 22px auto 0 auto;
        }
        .qr-box img { width: 100%; height: 100%; }

        .footer-note {
            margin: 18px auto 0 auto;
            text-align: center;
            font-size: {{ $px(15) }}px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: inherit;
            max-width: 520px;
            line-height: 1.4;
            border-top: 1px solid {{ $borderColor }};
            padding-top: 12px;
        }

        .rsvp {
            text-align: center;
            margin-top: 16px;
            color: inherit;
        }
        .rsvp .label {
            font-size: {{ $px(14) }}px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .rsvp .url {
            font-size: {{ $px(20) }}px;
            font-weight: 800;
            word-break: break-word;
            line-height: 1.3;
        }
    </style>
</head>
<body>
<div class="page-bg" style="
    @if($pdfBgImage)
        background-image: url('{{ $pdfBgImage }}');
        background-repeat: no-repeat;
        background-position: center center;
        background-size: cover;
    @else
        background-color: {{ $pdfBgColor ?: '#111' }};
    @endif
"></div>
<div class="overlay"></div>

<div class="frame">
    <div class="border-outer">
        <div class="border-inner">
            <div class="title">{{ $invitationCategoryTitle }}</div>
            <div class="subtitle">Invitation</div>

            <div class="dear">Dear {{ $recipientName ?: 'Guest' }},</div>

            <div class="invite-line">
                You are cordially invited as {{ $inviteAs }} to
            </div>
            <div class="event-name">{{ $eventTitle }}</div>

            @if(!empty($optionalMessage))
                <div class="optional-message">{{ $optionalMessage }}</div>
            @endif

            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value">{{ $eventAtText ?: '—' }}</span>
                </div>
                @if(!empty($eventLocation))
                    <div class="detail-row">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">{{ $eventLocation }}</span>
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
</div>
</body>
</html>

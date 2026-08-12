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

        $rsvpValue = !empty($rsvp) ? trim((string) $rsvp) : (string) ($acceptUrl ?? '');
        $rsvpValue = $rsvpValue !== '' ? $rsvpValue : (string) ($acceptUrl ?? '');
        $rsvpLabel = !empty($rsvp) ? 'RSVP:' : 'RSVP / View:';
        $invitationCategoryTitle = !empty($categoryName) ? $categoryName : 'Invitation';
    @endphp
    <style type="text/css">
        * { font-family: "Palatino Linotype", "Book Antiqua", Palatino, DejaVu Serif, DejaVu Sans, sans-serif; }
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
            background: rgba(0,0,0,0.45);
        }

        .frame {
            position: relative;
            z-index: 2;
            padding: 28px;
        }

        .border-outer {
            border: none;
            padding: 10px;
        }
        .border-inner {
            border: none;
            padding: 22px 22px 28px 22px;
            min-height: 740px;
            color: {{ $fontColor }};
        }
        .border-inner * { color: inherit; }

        .title {
            text-align: center;
            letter-spacing: 2px;
            font-size: 44px;
            font-weight: 700;
            margin-top: 10px;
            margin-bottom: 4px;
            text-transform: uppercase;
            color: {{ $fontColor }};
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
            border-top: 1px solid {{ $borderColor }};
            border-bottom: 1px solid {{ $borderColor }};
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
            border: 2px solid {{ $borderColor }};
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
            <div class="message">
                You are invited as a {{ !empty($categoryName) ? $categoryName : 'Guest' }} to our {{ $eventName }}
            </div>
            @if(!empty($optionalMessage))
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
                    @if(!empty($eventLocation))
                        <tr>
                            <td class="label">Venue:</td>
                            <td class="value">{{ $eventLocation }}</td>
                        </tr>
                    @endif
                    @if(!empty($recipientPhone))
                        <tr>
                            <td class="label">Phone:</td>
                            <td class="value">{{ $recipientPhone }}</td>
                        </tr>
                    @endif
                    @if(!empty($recipientEmail))
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
</div>
</body>
</html>

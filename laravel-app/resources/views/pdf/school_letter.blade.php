<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $parts['subject'] !== '' ? $parts['subject'] : $parts['heading'] }}</title>
    <style>
        @page { margin: 28px 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2a44; font-size: 13px; line-height: 1.45; }
        .band { width: 100%; margin: 0 0 12px; }
        .band img { width: 100%; height: auto; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 12px; color: #0b3f90; }
        .meta { margin: 0 0 14px; }
        h2 { font-size: 14px; margin: 0 0 12px; }
        .body { white-space: pre-wrap; }
        .footer { margin-top: 18px; white-space: pre-wrap; }
    </style>
</head>
<body>
    @if(!empty($headerImage))
        <div class="band"><img src="{{ $headerImage }}" alt=""></div>
    @elseif($parts['heading'] !== '')
        <h1>{{ $parts['heading'] }}</h1>
    @endif
    <div class="meta">
        @if($parts['reference'] !== '')
            Ref: {{ $parts['reference'] }}<br>
        @endif
        {{ $parts['date'] }}
    </div>
    @if($parts['subject'] !== '')
        <h2>Subject: {{ $parts['subject'] }}</h2>
    @endif
    <div class="body">{{ $parts['body'] }}</div>
    @if($parts['footer'] !== '')
        <div class="footer">{{ $parts['footer'] }}</div>
    @endif
    @if(!empty($footerImage))
        <div class="band" style="margin-top:18px;"><img src="{{ $footerImage }}" alt=""></div>
    @endif
</body>
</html>

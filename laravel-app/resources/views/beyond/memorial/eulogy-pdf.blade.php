<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1408; font-size: 13px; line-height: 1.6; }
        .kicker { letter-spacing: 2px; text-transform: uppercase; color: #8a6d1d; font-size: 10px; margin: 0 0 6px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .author {
            font-size: 16px; font-weight: bold; margin: 18px 0 6px; color: #1a1408;
            border-bottom: 1px solid #d4c4a0; padding-bottom: 8px;
        }
        .sub { color: #5a4a2a; margin: 0 0 16px; font-size: 11px; }
        .body p { margin: 0 0 12px; font-size: 14px; line-height: 1.75; }
        .sign-off { margin-top: 22px; font-size: 15px; font-style: italic; }
        .foot { margin-top: 28px; font-size: 11px; color: #6a5a40; border-top: 1px solid #d4c4a0; padding-top: 10px; }
    </style>
</head>
<body>
    <p class="kicker">In loving memory</p>
    <h1>Your eulogy for Pa Ngwayu Francis</h1>
    <p class="author">{{ $eulogy->name }}</p>
    <p class="sub">{{ optional($eulogy->created_at)->timezone('Africa/Douala')->format('d M Y H:i') }}</p>
    <div class="body">
        @foreach(preg_split("/\n{2,}|\n/", trim(str_replace(["\r\n", "\r"], "\n", (string) $eulogy->body))) as $para)
            @if(trim($para) !== '')
                <p>{{ trim($para) }}</p>
            @endif
        @endforeach
    </div>
    <p class="sign-off">{{ $eulogy->name }}</p>
    <p class="foot">A copy of the eulogy you submitted at beyondtechworld.com/pangwayu/remember</p>
</body>
</html>

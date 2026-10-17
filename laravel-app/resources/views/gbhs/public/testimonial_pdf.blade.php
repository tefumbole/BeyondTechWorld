<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #12261a; font-size: 13px; line-height: 1.6; }
        .kicker { letter-spacing: 2px; text-transform: uppercase; color: #1d6a3a; font-size: 10px; margin: 0 0 6px; }
        h1 { font-size: 22px; margin: 0 0 4px; color: #0c3a22; }
        .author { font-size: 16px; font-weight: bold; margin: 18px 0 6px; border-bottom: 1px solid #d7deea; padding-bottom: 8px; }
        .sub { color: #3d5346; margin: 0 0 16px; font-size: 11px; }
        .body p { margin: 0 0 12px; font-size: 14px; line-height: 1.75; }
        .sign { margin-top: 18px; }
        .sign img { height: 70px; }
        .foot { margin-top: 28px; font-size: 11px; color: #3d5346; border-top: 1px solid #d7deea; padding-top: 10px; }
    </style>
</head>
<body>
    <p class="kicker">GBHS Kumbo Ex-Students Association</p>
    <h1>Your testimony about GBHS Kumbo</h1>
    <p class="author">{{ $row->name }}</p>
    <p class="sub">{{ optional($row->created_at)->timezone('Africa/Douala')->format('d M Y H:i') }}</p>
    <div class="body">
        @foreach($row->paragraphs() as $para)
            <p>{{ $para }}</p>
        @endforeach
    </div>
    @if($row->signature_path && is_file(public_path($row->signature_path)))
        <div class="sign">
            <img src="{{ public_path($row->signature_path) }}" alt="Signature">
        </div>
    @endif
    <p class="foot">A copy of the testimony you submitted for GBHS Kumbo.</p>
</body>
</html>

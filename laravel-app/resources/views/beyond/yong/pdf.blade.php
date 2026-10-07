<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Induction invitation</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0b245c; margin: 0; }
        img.card { width: 100%; height: auto; }
        .donate { margin-top: 14px; text-align: center; }
        .donate a { color: #0b245c; font-size: 16px; font-weight: bold; }
        p { text-align: center; font-size: 13px; margin: 8px 0 0; }
    </style>
</head>
<body>
    @if($imageData !== '')
        <img class="card" src="{{ $imageData }}" alt="Invitation">
    @endif
    <div class="donate">
        <p>{{ $invitation->typeLabel() }} invitation for {{ $invitation->name }}</p>
        <p>Pledge: {{ $invitation->pledgeLabel() }}</p>
        <p><a href="{{ $donateUrl }}">Donate {{ $invitation->pledgeLabel() }}</a></p>
    </div>
</body>
</html>

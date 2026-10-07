<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your invitation</title>
    <style>
        body { margin: 0; font-family: "Source Sans Pro", sans-serif; background: #071433; color: #fff; }
        .wrap { width: min(720px, calc(100% - 20px)); margin: 0 auto; padding: 20px 0 32px; text-align: center; }
        img { width: 100%; border-radius: 16px; }
        a.btn { display: inline-block; margin: 14px 6px 0; background: #d4af37; color: #1c160e; text-decoration: none; font-weight: 700; border-radius: 999px; padding: 12px 18px; }
        p { color: #e8eef8; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $invitation->typeLabel() }} invitation</h1>
    <p>{{ $sent ? 'It has been sent to your WhatsApp.' : 'We saved your invitation. WhatsApp could not send it just now — you can still keep this copy.' }}</p>
    <img src="{{ $invitation->imageUrl() }}" alt="Invitation for {{ $invitation->name }}">
    <p>
        <a class="btn" href="{{ url('/yong/card/'.$invitation->id.'/download') }}">Download invitation</a>
        @if($invitation->food_file && is_file($invitation->foodPath()))
            <a class="btn" href="{{ url('/yong/card/'.$invitation->id.'/food') }}">Download food ticket</a>
        @endif
    </p>
    @if($invitation->isPremium())
        <p>Pledge: {{ $invitation->pledgeLabel() }}</p>
        <a class="btn" href="{{ url('/yong/donate/'.$invitation->id) }}">Donate</a>
    @endif
    <p><a href="{{ url('/yong') }}" style="color:#f3dd8a;">Back</a></p>
</div>
</body>
</html>

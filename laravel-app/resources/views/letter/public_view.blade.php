<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data->subject }} · {{ $general_setting->site_title ?? 'Letter' }}</title>
    <link rel="stylesheet" href="{{ asset('public/vendor/bootstrap/css/bootstrap.min.css') }}">
    <style>
        body { background: #f3f6fb; color: #1f2a44; font-family: "Nunito", sans-serif; }
        .letter-public-card {
            max-width: 920px;
            margin: 24px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(11, 63, 144, 0.08);
            padding: 28px;
        }
        .letter-public-meta { color: #6f7b91; font-size: 14px; margin-bottom: 18px; }
        .letter-public-subject { color: #0b3f90; font-weight: 800; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="letter-public-card">
        <div class="letter-public-meta">
            Reference: {{ $data->reference }}
            @if($data->category)
                · {{ $data->category->name }}
            @endif
        </div>
        <h1 class="letter-public-subject h4">{{ $data->subject }}</h1>
        @include('letter.letter_body')
    </div>
</body>
</html>

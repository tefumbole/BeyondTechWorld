<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invitation</title>

    <link rel="stylesheet" href="{{ asset('public/vendor/bootstrap/css/bootstrap.min.css') }}" type="text/css">
    <link rel="stylesheet" href="{{ asset('public/vendor/font-awesome/css/font-awesome.min.css') }}" type="text/css">
    <link rel="stylesheet" href="{{ asset('public/vendor/dripicons/webfont.css') }}" type="text/css">

    <style>
        body { margin: 0; background: #000; }
        .invite-wrap { padding: 0; }
    </style>
</head>
<body>
    <div class="invite-wrap">
        @yield('content')
    </div>
</body>
</html>

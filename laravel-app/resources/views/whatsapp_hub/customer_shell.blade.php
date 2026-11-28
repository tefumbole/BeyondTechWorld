<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b3d91">
    <title>Whats MAI</title>
    <link rel="manifest" href="{{ asset('whats-mai.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ asset('branding/mbole-ai.png') }}">
</head>
<body style="margin:0;background:#fff;">
    @yield('content')
</body>
</html>

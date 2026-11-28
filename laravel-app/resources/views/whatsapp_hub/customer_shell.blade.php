<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b3d91">
    <title>Whats MAI</title>
    <link rel="manifest" href="{{ route('whatsapp.mai.manifest') }}">
    <link rel="icon" type="image/png" href="/public/branding/mai-icon-192.png">
</head>
<body style="margin:0;background:#fff;">
    @yield('content')
    <script src="/public/js/mai-install.js?v={{ \App\Support\AppVersion::erp() }}" defer></script>
</body>
</html>

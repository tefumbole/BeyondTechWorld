<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Whats MAI</title>
    @include('cloud.portal.mai_install_head')
</head>
<body style="margin:0;background:#fff;">
    @yield('content')
</body>
</html>

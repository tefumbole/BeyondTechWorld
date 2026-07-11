<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $general_setting->site_title }}</title>
    @include('pdf.partials._letter_branded_styles')
</head>
<body>
@include('pdf.partials._letter_branded_open')
@include('pdf.partials._letter_branded_inner')
@include('pdf.partials._letter_branded_close')
</body>
</html>

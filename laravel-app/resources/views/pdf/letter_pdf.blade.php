<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $general_setting->site_title }}</title>
    @php
        // In-flow letterhead so Ref/To/Dear never overlap the branded header image.
        $letterhead_flow = true;
    @endphp
    @include('pdf.partials._letter_branded_styles')
</head>
<body>
@include('pdf.partials._letter_branded_open')
@include('pdf.partials._letter_branded_inner')
@include('pdf.partials._letter_branded_close')
</body>
</html>

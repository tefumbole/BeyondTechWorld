<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $general_setting->site_title }}</title>
    @include('pdf.partials._letter_branded_styles')
    <style type="text/css">
        .letter-recipient-break { page-break-after: always; }
    </style>
</head>
<body>
@include('pdf.partials._letter_branded_open')
@php
    $numItems = $people_type === 'csv' ? count($recipients ?? []) : count(explode(",", $data->to));
    $i = 0;
@endphp
@if($people_type === 'csv')
    @foreach(($recipients ?? []) as $user_to)
        @php $to = null; @endphp
        @include('pdf.partials._letter_branded_inner')
        @if(++$i != $numItems)
            <div class="letter-recipient-break"></div>
        @endif
    @endforeach
@else
    @foreach(explode(",", $data->to) as $to)
        @php $user_to = null; @endphp
        @include('pdf.partials._letter_branded_inner')
        @if(++$i != $numItems)
            <div class="letter-recipient-break"></div>
        @endif
    @endforeach
@endif
@include('pdf.partials._letter_branded_close')
</body>
</html>

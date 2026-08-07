<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $general_setting->site_title }}</title>
    @php
        $letterhead_flow = true;
    @endphp
    @include('pdf.partials._letter_branded_styles')
    <style type="text/css">
        .letter-recipient-break { page-break-after: always; }
    </style>
</head>
<body>
@include('pdf.partials._letter_branded_open')
@php
    $people_type = $people_type ?? ($data->people_type ?? '');
    $i = 0;
@endphp
@if($people_type === 'csv')
    @php $numItems = count($recipients ?? []); @endphp
    @foreach(($recipients ?? []) as $user_to)
        @php $to = null; @endphp
        @include('pdf.partials._letter_branded_inner')
        @if(++$i != $numItems)
            <div class="letter-recipient-break"></div>
        @endif
    @endforeach
@elseif($people_type === 'directory')
    @php
        $dirRecipients = \App\Support\LetterRecipients::decodePeopleJson($data->recipients_json);
        $numItems = count($dirRecipients);
    @endphp
    @foreach($dirRecipients as $person)
        @php
            $user_to = \App\Support\LetterRecipients::toSendObject($person);
            $to = $person['id'] ?? null;
        @endphp
        @include('pdf.partials._letter_branded_inner')
        @if(++$i != $numItems)
            <div class="letter-recipient-break"></div>
        @endif
    @endforeach
@else
    @php $numItems = count(array_filter(explode(',', (string) $data->to))); @endphp
    @foreach(explode(',', (string) $data->to) as $to)
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

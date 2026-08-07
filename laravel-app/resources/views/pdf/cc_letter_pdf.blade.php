<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $general_setting->site_title }}</title>
    @include('pdf.partials._letter_branded_styles')
</head>
<body>
@include('pdf.partials._letter_branded_open')
@php
    if ($data->people_type == "customer") {
        $user_class = \App\Customer::class;
    } else {
        $user_class = \App\Employee::class;
    }
@endphp

@if($general_setting->invoice_format != 'beyond_a4' && !empty($general_setting->site_logo))
    <div style="text-align:right;margin-bottom:10px;">
        <img src="{{ public_path('logo/') . $general_setting->site_logo }}" height="80" alt="">
    </div>
@endif

@php
    $data->rendered_header = isset($data->rendered_header) ? $data->rendered_header : $data->header;
    $data->rendered_body = isset($data->rendered_body) ? $data->rendered_body : $data->body;
    $data->rendered_footer = isset($data->rendered_footer) ? $data->rendered_footer : $data->footer;
    $user_to = null;
    $to = null;
    $people = array_filter(explode(',', (string) $data->to));
    if (count($people)) {
        $to = trim($people[0]);
        $user_to = $user_class::find($to);
    }
@endphp
@include('pdf.partials._letter_branded_inner')

@include('pdf.partials._letter_branded_close')
</body>
</html>

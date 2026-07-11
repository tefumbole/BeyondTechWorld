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

<div class="header" style="position:relative;">
    @if($data->is_edit == 1)
        @php $edit = \App\User::find($data->edit_by); @endphp
        @if($edit && $edit->stemp)
            <img class="edit" src="{{ public_path('images/user/') . $edit->stemp }}" style="max-height:18px;width:auto;">
        @endif
    @endif
    @if($data->is_approve == 1)
        @php $approve = \App\User::find($data->approved_by); @endphp
        @if($approve && $approve->approve)
            <img class="approve" src="{{ public_path('images/user/') . $approve->approve }}" style="max-height:18px;width:auto;">
        @endif
    @endif
    <span class="header-letter">{!! isset($data->rendered_header) ? $data->rendered_header : $data->header !!}</span>
</div>

<div class="letter-meta">
    Ref: {{ $data->reference }}<br>
    {{ date('M d, Y') }}
</div>

<div style="margin-top:10px;">
    @php
        foreach (explode(",", $data->to) as $to) {
            echo $user_class::find($to) ? 'Dear ' . $user_class::find($to)->name . ', ' : '';
        }
    @endphp
</div>

<div class="letter-body">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
    {!! isset($data->rendered_body) ? $data->rendered_body : $data->body !!}
</div>

<div class="letter-signature-row">
    <div class="letter-codes-back">
        <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($data->reference, 'C128') }}" width="280" alt="barcode"><br>
        <img src="data:image/png;base64,{{ DNS2D::getBarcodePNG(\App\Support\LetterQr::scanUrl($data), 'QRCODE') }}" width="90" alt="qr">
    </div>
    <div class="letter-signature-left">
        <p>Sincerely,</p>
        @if($data->is_sign == 1)
            @php $signer = \App\User::find($data->signed_by); @endphp
            @if($signer && $signer->sign)
                <img src="{{ public_path('images/user/') . $signer->sign }}" style="max-height:36px;width:auto;">
            @endif
        @endif
    </div>
</div>

<div class="letter-footer-text">
    @if($data->footer != null)
        {!! isset($data->rendered_footer) ? $data->rendered_footer : $data->footer !!}
    @else
        {{ $data->name }}
    @endif
    @if($data->cc)
        <h5>CC:
            @foreach(explode(',', $data->cc) as $cc)
                {{ $user_class::find($cc) ? $user_class::find($cc)->name . ', ' : '' }}
            @endforeach
        </h5>
    @endif
    @if($data->attachment)
        <h5>Files: {{ $data->attachmentLib ? count($data->attachmentLib) : 1 }}</h5>
    @endif
</div>

@include('pdf.partials._letter_branded_close')
</body>
</html>

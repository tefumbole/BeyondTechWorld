@php
    use App\Support\LetterSignature;
    use App\Support\LetterQr;

    $peopleType = $data->people_type;
    if ($peopleType == 'customer' || $peopleType == 'all') {
        $user_class = \App\Customer::class;
    } elseif ($peopleType == 'user') {
        $user_class = \App\Employee::class;
    } else {
        $user_class = \App\Customer::class;
    }

    if (!isset($user_to)) {
        $user_to = null;
    }
    if (!$user_to && !empty($to) && in_array($peopleType, ['customer', 'user'], true)) {
        $user_to = $user_class::find($to);
    }

    $replacements = [
        '[name]' => optional($user_to)->name ?? '',
        '[phone_number]' => optional($user_to)->phone_number ?? '',
        '[email]' => optional($user_to)->email ?? '',
        '[address]' => optional($user_to)->address ?? '',
        '[Column1]' => optional($user_to)->column1 ?? '',
        '[Column2]' => optional($user_to)->column2 ?? '',
        '[Column3]' => optional($user_to)->column3 ?? '',
        '[Column4]' => optional($user_to)->column4 ?? '',
        '[Column5]' => optional($user_to)->column5 ?? '',
        '[Column6]' => optional($user_to)->column6 ?? '',
        '[Column7]' => optional($user_to)->column7 ?? '',
        '[Column8]' => optional($user_to)->column8 ?? '',
        '[Column9]' => optional($user_to)->column9 ?? '',
        '[Column10]' => optional($user_to)->column10 ?? '',
        '[column1]' => optional($user_to)->column1 ?? '',
        '[column2]' => optional($user_to)->column2 ?? '',
        '[column3]' => optional($user_to)->column3 ?? '',
        '[column4]' => optional($user_to)->column4 ?? '',
        '[column5]' => optional($user_to)->column5 ?? '',
        '[column6]' => optional($user_to)->column6 ?? '',
        '[column7]' => optional($user_to)->column7 ?? '',
        '[column8]' => optional($user_to)->column8 ?? '',
        '[column9]' => optional($user_to)->column9 ?? '',
        '[column10]' => optional($user_to)->column10 ?? '',
    ];

    $rendered_header = strtr((string) $data->header, $replacements);
    $rendered_body = strtr((string) $data->body, $replacements);
    $rendered_footer = strtr((string) ($data->footer ?? ''), $replacements);

    $editUser = $data->edit_by ? \App\User::find($data->edit_by) : null;
    $editPath = LetterSignature::path($data->edit_signature)
        ?: ($editUser && $editUser->stemp ? public_path('images/user/' . $editUser->stemp) : null);

    $approveUser = $data->approved_by ? \App\User::find($data->approved_by) : null;
    $approvePath = LetterSignature::path($data->approve_signature)
        ?: ($approveUser && $approveUser->approve ? public_path('images/user/' . $approveUser->approve) : null);

    $signUser = $data->signed_by ? \App\User::find($data->signed_by) : null;
    $signPath = LetterSignature::path($data->sign_signature)
        ?: ($signUser && $signUser->sign ? public_path('images/user/' . $signUser->sign) : null);
@endphp

@if($general_setting->invoice_format != 'beyond_a4' && !empty($general_setting->site_logo))
    <div style="text-align:right;margin-bottom:10px;">
        <img src="{{ public_path('logo/') . $general_setting->site_logo }}" height="80" alt="">
    </div>
@endif

<div class="header" style="position:relative;">
    @if($data->is_edit == 1 && $editPath)
        <img class="edit" src="{{ $editPath }}" style="max-height:18px;width:auto;">
    @endif
    @if($data->is_approve == 1 && $approvePath)
        <img class="approve" src="{{ $approvePath }}" style="max-height:18px;width:auto;">
    @endif
    <span class="header-letter">{!! $rendered_header !!}</span>
</div>

<div class="letter-meta">
    Ref: {{ $data->reference }}<br>
    {{ date('M d, Y') }}
</div>

@if($user_to)
    <div>{{ $user_to->name }}<br>{{ $user_to->address }}</div>
    <div style="margin-top:10px;">Dear: {{ $user_to->name }},</div>
@endif

<div class="letter-body">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
    {!! $rendered_body !!}
</div>

<div class="letter-signature-row">
    <div class="letter-codes-back">
        <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($data->reference, 'C128') }}" width="280" alt="barcode"><br>
        <img src="data:image/png;base64,{{ DNS2D::getBarcodePNG(\App\Support\LetterQr::scanUrl($data), 'QRCODE') }}" width="90" alt="qr">
    </div>
    <div class="letter-signature-left">
        <p>Sincerely,</p>
        @if($data->is_sign == 1 && $signPath)
            <img src="{{ $signPath }}" style="max-height:36px;width:auto;">
        @endif
    </div>
</div>

<div class="letter-footer-text">
    @if($data->footer != null)
        {!! $rendered_footer !!}
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
        <h5>Files: {{ (isset($data->attachmentLib) && count($data->attachmentLib) > 0) ? count($data->attachmentLib) : 1 }}</h5>
    @endif
</div>

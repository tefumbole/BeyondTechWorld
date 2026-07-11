@php
    if ($people_type == "customer") {
        $user_class = \App\Customer::class;
        $user_to = $user_to ?: \App\Customer::find($to);
    } else if($people_type == "user") {
        $user_class = \App\Employee::class;
        $user_to = $user_to ?: \App\Employee::find($to);
    } else {
        // CSV: $user_to is already an object from recipients
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
    $rendered_header = strtr($data->header, $replacements);
    $rendered_body = strtr($data->body, $replacements);
    $rendered_footer = strtr($data->footer, $replacements);
@endphp

@if($general_setting->invoice_format == 'beyond_a4')
    <img src="{{public_path('logo/') . $general_setting->email_header}}" style=" width: 100%;">
    <div style="max-width:95vw;margin:0 auto; ">
@else
    <div style="max-width:1400px;margin:0 auto; ">
@endif
    <div id="receipt-data">
        @if($general_setting->invoice_format != 'beyond_a4')
            <div class="logo">
                @if($general_setting->site_logo)
                    <img src="{{public_path('logo/') . $general_setting->site_logo}}" height="100" style="margin:10px 0;filter: brightness(0);">
                @endif
            </div>
        @endif

        <div class="header">
            @if($data->is_edit == 1)
                @php $edit = \App\User::find($data->edit_by); @endphp
                <img class="edit" src="{{public_path('images/user/') . $edit->stemp}}" height="40vw">
            @endif
            @if($data->is_approve == 1)
                @php $approve = \App\User::find($data->approved_by); @endphp
                <img class="approve" src="{{public_path('images/user/') . $approve->approve}}" height="40vw">
            @endif
            <span class ="header-letter">{!! $rendered_header !!}</span>
        </div>

        <br><br>
        <div>Ref: {{ $data->reference }} <br>
            {{ date('M d, Y') }}</div><br>

        <div>
            @if($user_to)
                {{ $user_to->name }}<br>
                {{ $user_to->address }}<br>
            @endif
        </div><br>

        <div>Dear:
            @php echo $user_to ? $user_to->name .  ', ' : ''; @endphp
        </div>
        <div class="card-body" id="letter-body" style="text-transform: uppercase">
            <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
        </div>
        {!! $rendered_body !!}
        <br>
        <p>Sincerely, </p>
        <div class="row">
            <div class="pull-left">
                @if($data->is_sign == 1)
                    @php $approve = \App\User::find($data->signed_by); @endphp
                    <img src="{{public_path('images/user/') . $approve->sign}}" height="50vw">
                @endif
            </div>
        </div>
        <br><br><br>
        @if($data->footer != null)
            {!! $rendered_footer !!}
        @else
            {{ $data->name }}
        @endif

        <h5>CC:
            @php
                if (!empty($data->cc)) {
                    foreach (explode(",", $data->cc) as $cc) {
                        echo isset($user_class) && $user_class::find($cc) ? $user_class::find($cc)->name .  ', ' : '';
                    }
                }
            @endphp
        </h5>
    </div>
</div>

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

    $rendered_header = \App\Support\LetterPlaceholders::replace($data->header, $user_to);
    $rendered_body = \App\Support\LetterPlaceholders::replace($data->body, $user_to);
    $rendered_footer = \App\Support\LetterPlaceholders::replace($data->footer, $user_to);
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

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
    <title>{{$general_setting->site_title}}</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="all,follow">

    <style type="text/css">
        .logo {
            text-align: right;
            display: inline;
            margin-left: 268px;
            margin-right: 0;
            padding-right: 0;
        }
        .pull-left {
            float: left;
            margin-left: 0px;
        }
        .header{
            float: right;
            margin-left: 0px;
            padding-left: 0px;
            text-align: center;
            font-size: 10px;
        }
        .pull-right {
            float: right;
            margin-right: 200px;
        }
        .waterm-mark {
            width: 20%;
            position: absolute;
            top: 40%;
            right: 330px;
            opacity: 0.3;
        }
        table {width: 100%;}
        tfoot tr th:first-child {text-align: left;}

        .centered {
            text-align: center;
            align-content: center;
        }
        .edit{
            position: absolute;
            margin-top: -20px;
            margin-left: 85px;
            margin-right: 0px;
            padding-right: 0px;
            z-index: 0;
            opacity: 0.5;
        }
        .approve{
            position: absolute;
            margin-top: -20px;
            margin-left: 30px;
            z-index: 0;
            opacity: 0.5;
        }
        .header-letter{
            top: 40px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach ($ids as $id)
    @php
        $letter = \App\Letter::find($id);
        $people_type = $letter->people_type;
        if($letter->people_type == 'customer') {
            $customer = \App\Customer::class;
        } else {
            $customer = \App\Employee::class;
        }

    @endphp
    @foreach(explode(",", $letter->to) as $to)

        @if($general_setting->invoice_format == 'beyond_a4')
            <img src="{{public_path('logo/') . $general_setting->email_header}}" style=" width: 100%;">
            {{--    <img src="{{public_path('logo/') . $general_setting->email_water_mark}}" class="waterm-mark">--}}
            <div style="max-width:95vw;margin:0 auto; ">
                @else
                    <div style="max-width:1400px;margin:0 auto; ">
                        @endif
                        @php
                            if ($people_type == "customer") {
                                $user_class = \App\Customer::class;
                                $user_to = \App\Customer::find($to);
                            } else {
                                $user_class = \App\Employee::class;
                                $user_to = \App\Employee::find($to);
                            }
                        @endphp
                        <div id="receipt-data">
                            @if($general_setting->invoice_format != 'beyond_a4')
                                <div class="logo">
                                    @if($general_setting->site_logo)
                                        <img src="{{public_path('logo/') . $general_setting->site_logo}}" height="100" style="margin:10px 0;filter: brightness(0);">
                                    @endif
                                </div>
                            @endif

                            <div class="header">
                                @if($letter->is_edit == 1)
                                    @php
                                        $edit = \App\User::find($letter->edit_by);
                                    @endphp
                                    <img class="edit" src="{{public_path('images/user/') . $edit->stemp}}" height="40vw">
                                @endif
                                @if($letter->is_approve == 1)
                                    @php
                                        $approve = \App\User::find($letter->approved_by);
                                    @endphp
                                    <img class="approve" src="{{public_path('images/user/') . $approve->approve}}" height="40vw">
                                @endif
                                <span class ="header-letter">{!! $letter->header !!}</span>

                            </div>

                            <br><br>
                            <div>Ref: {{ $letter->reference }} <br>
                                {{ date('M d, Y') }}</div><br>

                            <div>
                                @if($user_to)
                                    {{ $user_to->name }}<br>
                                    {{ $user_to->address }}<br>
                                @endif
                            </div><br>

                            <div>Dear:
                                @php
                                    echo $user_to ? $user_to->name .  ', ' : '';

                                @endphp
                            </div>
                            <div class="card-body" id="letter-body" style="text-transform: uppercase">
                                <h2>Subject: <span style="text-decoration: underline;">{{ $letter->subject }}</span></h2>
                            </div>
                            {!! $letter->body !!}
                            <br>
                            <p>Sincerely, </p>
                            <div class="row">
                                <div class="pull-left">
                                    @if($letter->is_sign == 1)
                                        @php
                                            $approve = \App\User::find($letter->signed_by);
                                        @endphp
                                        <img src="{{public_path('images/user/') . $approve->sign}}" height="50vw">
                                    @endif
                                </div>
                            </div>
                            <br><br><br>
                            @if($letter->footer != null)
                                {!! $letter->footer !!}
                            @else
                                {{ $letter->name }}
                            @endif

                            <h5>CC:
                                @php
                                    foreach (explode(",", $letter->cc) as $cc) {
                                        echo $user_class::find($cc) ? $user_class::find($cc)->name .  ', ' : '';
                                    }
                                @endphp
                            </h5>
                        </div>
                    </div>
            </div>
            <table>
                <tbody>
                <tr>
                    <td class="centered" colspan="3">
                            <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS1D::getBarcodePNG($letter->reference, 'C128') . '" width="300" alt="barcode"   />';?>
                        <br>
                            <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS2D::getBarcodePNG(\App\Support\LetterQr::scanUrl($letter), 'QRCODE') . '" alt="barcode"   />';?>
                    </td>
                </tr>
                </tbody>
            </table>
            @if($general_setting->invoice_format == 'beyond_a4')
                <div class="lastPage" >
                    <img id="print-footer" src="{{public_path('logo/') . $general_setting->email_footer}}" style=" width: 100%;">
                </div>
            @endif
            <div class="page-break"></div>
        @endforeach
    @endforeach
</body>
</html>

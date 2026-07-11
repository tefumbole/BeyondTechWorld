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
        .align-items-center{
            text-align: center;
        }
        .pull-left {
            float: left;
            margin-left: 200px;
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
    </style>
</head>
<body>

@if($general_setting->invoice_format == 'beyond_a4')
    <img src="{{ env('APP_URL') . '/public/logo/' . $general_setting->email_header }}" style=" width: 100%;">
    {{--    <img src="{{public_path('logo/') . $general_setting->email_water_mark}}" class="waterm-mark">--}}
    <div style="max-width:95vw;margin:0 auto; ">
        @else
            <div style="max-width:1000px;margin:0 auto; ">
                @endif
                <div id="receipt-data">
                    @if($general_setting->invoice_format != 'beyond_a4')
                        <div class="align-items-center">
                            @if($general_setting->site_logo)
                                <img src="{{ env('APP_URL') . '/public/logo/' . $general_setting->site_logo }}" height="42" width="50" style="margin:10px 0;filter: brightness(0);">
                            @endif

                        </div>
                    @endif
                    <h6>Ref: {{ $data->reference }} <br>
                        {{ date('M d, Y') }}</h6>

                    {!! isset($rendered_header) ? $rendered_header : $data->header !!}
                    <h2>Dear:
                        @php
                            if ($data->people_type == "customer") {
                                $user = \App\Customer::class;
                            } else {
                                $user = \App\Employee::class;
                            }
                            echo $user::find($to) ? $user::find($to)->name .  ', ' : '';

                        @endphp
                    </h2>
                    {!! isset($rendered_body) ? $rendered_body : $data->body !!}
                    <br><br><br>
                    <div class="row">
                        <div class="pull-right">
                            @if($data->is_approve == 1)
                                @php
                                    $approve = \App\User::find($data->approved_by);
                                @endphp
                                <img src="{{ env('APP_URL') . '/public/images/user/' . $approve->stemp }}" height="100vw">
                            @endif
                        </div>
                        <div class="pull-left">
                            @if($data->is_sign == 1)
                                @php
                                    $approve = \App\User::find($data->signed_by);
                                @endphp
                                <img src="{{ env('APP_URL') . '/public/images/user/'.$approve->sign }}" height="100vw">
                            @endif
                        </div>
                    </div>
                    <br><br><br><br><br>
                    {!! isset($rendered_footer) ? $rendered_footer : $data->footer !!}

                </div>
            </div>
    </div>
    @if($general_setting->invoice_format == 'beyond_a4')
        <div class="lastPage" >
            <img id="print-footer" src="{{ env('APP_URL') . '/public/logo/' . $general_setting->email_footer}}" style=" width: 100%;">
        </div>
    @endif

</body>
</html>

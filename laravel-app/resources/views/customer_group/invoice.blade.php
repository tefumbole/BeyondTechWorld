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
        .waterm-mark {
            width: 20%;
            position: absolute;
            top: 40%;
            right: 330px;
            opacity: 0.3;
        }
        * {
            font-size: 14px;
            line-height: 24px;
            font-family: 'Ubuntu', sans-serif;
            text-transform: capitalize;
        }
        .btn {
            padding: 7px 10px;
            text-decoration: none;
            border: none;
            display: block;
            text-align: center;
            margin: 7px;
            cursor:pointer;
        }

        .btn-info {
            background-color: #999;
            color: #FFF;
        }

        .btn-primary {
            background-color: #6449e7;
            color: #FFF;
            width: 100%;
        }
        td,
        th,
        tr,
        table {
            border-collapse: collapse;
        }
        tr {border-bottom: 1px dotted #ddd;}
        td,th {padding: 7px 0;width: 50%;}

        table {width: 100%;}
        tfoot tr th:first-child {text-align: left;}

        .centered {
            text-align: center;
            align-content: center;
        }
        small{font-size:11px;}

        @media print {
            * {
                font-size:12px;
                line-height: 20px;
            }
            td,th {padding: 5px 0;}
            .hidden-print {
                display: none !important;
            }
            @page { margin: 0; } body { margin: 0.5cm; margin-bottom:1.6cm; }
            /*tbody::after {*/
            /*    content: '';*/
            /*    display: block;*/
            /*    page-break-after: always;*/
            /*    page-break-inside: always;*/
            /*    page-break-before: avoid;*/
            /*}*/
            #print-footer {
                bottom: 0;
            }
        }
    </style>
</head>
<body>
@if($general_setting->invoice_format == 'beyond_a4')
    <style>
        .btn {
            width: 25% !important;
        }
        .btn-info {
            float: right;
        }
        /*.lastPage {*/
        /*    page: last_page;*/
        /*    page-break-before: always; !* Use if your last page is blank, else omit. *!*/
        /*}*/

        /*@media print {*/
        /*    #print-footer {*/
        /*        position: absolute;*/
        /*        bottom: 0;*/
        /*        display: none;*/
        /*    }*/
        /*    @page last_page {*/
        /*        #print-footer {*/
        /*            position: relative;*/
        /*            display: inline-block;*/
        /*            top: 500px*/
        /*        }*/
        /*    }*/
        /*}*/
    </style>
    <img src="{{url('public/logo', $header)}}" style=" width: 100%;">
    <img src="{{url('public/logo', $water_mark)}}" class="waterm-mark">
    <div style="max-width:95vw;margin:0 auto; ">
        @else
            <div style="max-width:400px;margin:0 auto; ">
                @endif

                    @php $url = '../../customer_group'; @endphp

                <div class="hidden-print">
                    <table>
                        <tr>
                            <td><a href="{{$url}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> {{trans('file.Back')}}</a> </td>
                            <td><button onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> {{trans('file.Print')}}</button></td>
                        </tr>
                    </table>
                    <br>
                </div>

                <div id="receipt-data">
                    <div class="centered">

                        @if($general_setting->invoice_format == 'standard' || $general_setting->invoice_format == 'gst')
                            @if($general_setting->site_logo)
                                <img src="{{url('public/logo', $general_setting->site_logo)}}" height="42" width="50" style="margin:10px 0;filter: brightness(0);">
                            @endif
                            <h2>{{@$general_setting->site_title}}</h2>
                        @endif
                        @if($general_setting->invoice_format != 'mini')
                            <p>{{trans('file.Address')}}: {{@$deposit->user->address}}
                            <br>{{trans('file.Phone Number')}}: {{@$deposit->user->phone}}
                        @endif
                        </p>
                    </div>
                    <p>
                        @if($general_setting->invoice_format == 'mini'){{trans('file.Company Name')}}: {{$general_setting->site_title}}<br>@endif
                        {{trans('file.Date')}}: {{$deposit->created_at}}<br>
                        {{trans('file.reference')}}: {{$deposit->payment_reference}}<br>
                        Customer Group: {{$lims_customer_data->name}}<br>
                        @if($deposit->depositor_id)
                            Depositor: {{@$deposit->depositor->name}}<br>
                        @endif
                    </p>
                    <table class="table-data">
                        <tbody>
                        <?php $total_product_tax = 0;?>

                        <!-- <tfoot> -->
                        <tr>
                            <th colspan="2" style="text-align:left">{{trans('file.Total')}}</th>
                            <th style="text-align:right">{{number_format((float)$deposit->amount, 2)}}</th>
                        </tr>
                        <tr>
                            <th colspan="2" style="text-align:left">Payment Status</th>
                            <th style="text-align:right">
                                @if($deposit->status == 0)
                                    {{ trans('file.Pending') }}
                                @else
                                    {{ trans('file.Paid') }}
                                @endif
                            </th>
                        </tr>
                        <tr>
                            @if($general_setting->currency_position == 'prefix')
                                <th class="centered" colspan="3">{{trans('file.In Words')}}: <span>{{$currency->code}}</span> <span>{{str_replace("-"," ",$numberInWords)}}</span></th>
                            @else
                                <th class="centered" colspan="3">{{trans('file.In Words')}}: <span>{{str_replace("-"," ",$numberInWords)}}</span> <span>{{$currency->code}}</span></th>
                            @endif
                        </tr>
                        </tbody>
                        <!-- </tfoot> -->
                    </table>
                    @if($general_setting->invoice_format != 'mini')
                        <b>{{trans('file.Note')}}:</b> {!! $deposit->note !!}<br>
                        <table>
                        <tbody>
                            <tr style="background-color:#ddd;">
                                <td style="padding: 5px;width:30%">{{trans('file.Paid By')}}: {{$deposit->payment_method == 1 ? 'Cash' : 'Momo/Mtn'}}</td>
                                <td style="padding: 5px;width:40%">{{trans('file.Amount')}}: {{number_format((float)$deposit->amount, 2)}}</td>
                            </tr>
                        <tr><td class="centered" colspan="3">{{trans('file.Thank you for shopping with us. Please come again')}}</td></tr>
                        <tr>
                            <td class="centered" colspan="3">
                                <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS1D::getBarcodePNG($deposit->payment_reference, 'C128') . '" width="300" alt="barcode"   />';?>
                                <br>
                                <?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS2D::getBarcodePNG($deposit->payment_reference, 'QRCODE') . '" alt="barcode"   />';?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    @else
                        <center><?php echo '<img style="margin-top:10px;" src="data:image/png;base64,' . DNS1D::getBarcodePNG($deposit->payment_reference, 'C128') . '" width="300" alt="barcode"   />';?></center>
                    @endif
                    <!-- <div class="centered" style="margin:30px 0 50px">
            <small>{{trans('file.Invoice Generated By')}} {{$general_setting->site_title}}.
            {{trans('file.Developed By')}} Faby Developers</strong></small>
        </div> -->
                </div>
            </div>
            @if($general_setting->invoice_format == 'beyond_a4')
                <div class="lastPage" >
                    <img id="print-footer" src="{{url('public/logo', $footer)}}" style=" width: 100%;">
                </div>
            @endif
            <script type="text/javascript">
                var myItem = localStorage.getItem('pos-expend');
                localStorage.clear();
                localStorage.setItem('pos-expend',myItem);
                function auto_print() {
                    window.print()
                }
                setTimeout(auto_print, 1000);
            </script>

</body>
</html>

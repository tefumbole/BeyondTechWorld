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
            #print-footer {
                bottom: 0;
            }
        }

    </style>
</head>
<body>

@if($general_setting->invoice_format == 'beyond_a4')
    <img src="{{url('public/logo', $header)}}" style=" width: 100%;">
{{--    <img src="{{url('public/logo', $water_mark)}}" class="waterm-mark">--}}
    <div style="max-width:95vw;margin:0 auto; ">
        @else
    <div style="max-width:400px;margin:0 auto; ">
        @endif
        <div class="hidden-print">
            <table>
                <tr>
                    <td><a href="{{route('frontend.home')}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> {{trans('file.Back')}}</a> </td>
                    <td><button onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> {{trans('file.Print')}}</button></td>
                </tr>
            </table>
            <br>
        </div>
                <div id="receipt-data">
                    @if($general_setting->invoice_format != 'beyond_a4')
                    <div class="centered">
                        @if($general_setting->site_logo)
                            <img src="{{url('public/logo', $general_setting->site_logo)}}" height="42" width="50" style="margin:10px 0;filter: brightness(0);">
                        @endif


{{--                        <p>{{trans('file.Address')}}: {{$lims_warehouse_data->address}}--}}
{{--                            <br>{{trans('file.Phone Number')}}: {{$lims_warehouse_data->phone}}--}}
{{--                        </p>--}}
                    </div>
                    @endif
                    <p>{{trans('file.Date')}}: {{$lims_sale_data->created_at->format('D, M d, Y h:i:s')}}<br>
                        {{trans('file.reference')}}: {{$lims_sale_data->reference}}<br>
                        {{trans('file.customer')}}: {{$lims_customer_data->name}}<br>

                    </p>
                    <table class="table-data">
                        <tbody>
                        <?php $total_product_tax = 0;?>
                        @foreach($lims_product_sale_data as $key => $product_sale_data)
                                <?php
                                 $lims_product_data = \App\Product::find($product_sale_data->product_id);
                                 $product_name = $lims_product_data->name;
                                ?>
                            <tr>
                                <td colspan="2">{{$product_name}}
                                <br>
                                    {{$product_sale_data->quantity}} * {{number_format((float)$product_sale_data->price, 2)}}
                                </td>
                                <td style="text-align:right;vertical-align:bottom">{{number_format((float)$product_sale_data->sub_total, 2)}}</td>
                            </tr>
                        @endforeach

                        <!-- <tfoot> -->
                        <tr>
                            <th colspan="2" style="text-align:left">{{trans('file.Total')}}</th>
                            <th style="text-align:right">{{number_format((float)$lims_sale_data->grand_total, 2)}}</th>
                        </tr>
                        @if($general_setting->invoice_format == 'gst' && $general_setting->state == 1)
                            <tr>
                                <td colspan="2">IGST</td>
                                <td style="text-align:right">{{number_format((float)$total_product_tax, 2)}}</td>
                            </tr>
                        @elseif($general_setting->invoice_format == 'gst' && $general_setting->state == 2)
                            <tr>
                                <td colspan="2">SGST</td>
                                <td style="text-align:right">{{number_format((float)($total_product_tax / 2), 2)}}</td>
                            </tr>
                            <tr>
                                <td colspan="2">CGST</td>
                                <td style="text-align:right">{{number_format((float)($total_product_tax / 2), 2)}}</td>
                            </tr>
                        @endif
                        @if($lims_sale_data->order_tax)
                            <tr>
                                <th colspan="2" style="text-align:left">{{trans('file.Order Tax')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->order_tax, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->order_discount)
                            <tr>
                                <th colspan="2" style="text-align:left">{{trans('file.Order Discount')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->order_discount, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->coupon_discount)
                            <tr>
                                <th colspan="2" style="text-align:left">{{trans('file.Coupon Discount')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->coupon_discount, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->shipping_cost)
                            <tr>
                                <th colspan="2" style="text-align:left">{{trans('file.Shipping Cost')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->shipping_cost, 2)}}</th>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="2" style="text-align:left">{{trans('file.grand total')}}</th>
                            <th style="text-align:right">{{number_format((float)$lims_sale_data->grand_total, 2)}}</th>
                        </tr>
                        <tr>
                            <th colspan="2" style="text-align:left">Payment Status</th>
                            <th style="text-align:right">
                                @if($lims_sale_data->payment_status == 1)
                                    {{ trans('file.Paid') }}
                                @elseif($lims_sale_data->payment_status == 2)
                                    {{ trans('file.Due') }}
                                @elseif($lims_sale_data->payment_status == 3)
                                    {{ trans('file.Partial') }}
                                @else
                                    {{ trans('file.Pending') }}
                                @endif
                            </th>
                        </tr>
                        <tr>
                            <th colspan="2" style="text-align:left">Order Status</th>
                            <th style="text-align:right">
                                @if($lims_sale_data->order_status == 0)
                                    {{ trans('file.Pending') }}
                                @elseif($lims_sale_data->order_status == 1)
                                    Complete
                                @elseif($lims_sale_data->order_status == 2)
                                    Rejected
                                @endif
                            </th>
                        </tr>
                        <tr>
                            <th class="centered" colspan="1">{{trans('file.Amount')}}: <span>{{$currency->code}}</span> <span>{{number_format((float)$lims_sale_data->grand_total, 2)}}</span></th>
                            <th class="centered" colspan="2">{{trans('file.In Words')}}: <span>{{$currency->code}}</span> <span>{{str_replace("-"," ",$numberInWords)}}</span></th>
                        </tr>
                        </tbody>
                        <!-- </tfoot> -->
                    </table>
                    <table>
                        <tbody>
                        <tr>
                            <td class="centered" colspan="3">{{trans('file.Thank you for shopping with us. Please come again')}}</td>
                        </tr>
                        </tbody>
                    </table>
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

</body>

<script type="text/javascript">
    var myItem = localStorage.getItem('pos-expend');
    localStorage.clear();
    localStorage.setItem('pos-expend',myItem);
    function auto_print() {
        window.print()
    }
    setTimeout(auto_print, 1000);
</script>
</html>

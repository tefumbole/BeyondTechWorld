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
    </style>
    <img src="{{url('public/logo', $header)}}" style=" width: 100%;">
    <img src="{{url('public/logo', $water_mark)}}" class="waterm-mark">
    <div style="max-width:95vw;margin:0 auto; ">
        @else
            <div style="max-width:400px;margin:0 auto; ">
                @endif
                <div id="receipt-data">
                    <div class="centered">
                        @if($general_setting->site_logo)
                            <img src="{{url('public/logo', $general_setting->site_logo)}}" height="42" width="50" style="margin:10px 0;filter: brightness(0);">
                        @endif

                        <h2>{{@$lims_biller_data->company_name}}</h2>

                        <p>{{trans('file.Address')}}: {{$lims_warehouse_data->address}}
                            <br>{{trans('file.Phone Number')}}: {{$lims_warehouse_data->phone}}
                        </p>
                    </div>
                    <p>{{trans('file.Date')}}: {{$lims_sale_data->created_at}}<br>
                        {{trans('file.reference')}}: {{$lims_sale_data->reference_no}}<br>
                        {{trans('file.customer')}}: {{$lims_customer_data->name}}<br>
                    </p>
                    <table class="table-data">
                        <tbody>
                        <?php $total_product_tax = 0;?>
                        @foreach($lims_product_sale_data as $key => $product_sale_data)
                                <?php
                                if ($product_sale_data->multi_product_batch_id != null) {
                                    $multi_product_batch_id =  json_decode($product_sale_data->multi_product_batch_id);
                                    $multi_product_batch_qty =  json_decode($product_sale_data->multi_product_batch_qty);
                                }
                                $lims_product_data = \App\Product::find($product_sale_data->product_id);
                                if($product_sale_data->variant_id) {
                                    $variant_data = \App\Variant::find($product_sale_data->variant_id);
                                    $product_name = $lims_product_data->name.' ['.$variant_data->name.']';
                                }
                                elseif($product_sale_data->product_batch_id) {
                                    $product_batch_data = \App\ProductBatch::select('batch_no')->find($product_sale_data->product_batch_id);
                                    if (!@$multi_product_batch_id) {
                                        $product_name = $lims_product_data->name.' ['.trans("file.Batch No").':'.$product_batch_data->batch_no.']';
                                    } else {
                                        $product_name = $lims_product_data->name;

                                        foreach ($multi_product_batch_id as $key => $batch_id) {
                                            $product_batch_data = \App\ProductBatch::select('batch_no')->find($batch_id);
                                            $product_name .= ' ['.trans("file.Batch No").':'.$product_batch_data->batch_no . '×' . $multi_product_batch_qty[$key] . ']';
                                        }
                                    }
                                }
                                else
                                    $product_name = $lims_product_data->name;
                                ?>
                            <tr>
                                <td colspan="2">
                                    {{$product_name}}
                                    <br>{{$product_sale_data->qty}} x {{number_format((float)($product_sale_data->total / $product_sale_data->qty), 2)}}

                                    @if($product_sale_data->tax_rate)
                                            <?php $total_product_tax += $product_sale_data->tax ?>
                                        [{{trans('file.Tax')}} ({{$product_sale_data->tax_rate}}%): {{$product_sale_data->tax}}]
                                    @endif
                                </td>
                                <td>{{$product_sale_data->start}} | {{$product_sale_data->end}}</td>
                                <td style="text-align:right;vertical-align:bottom">{{number_format((float)$product_sale_data->total, 2)}}</td>
                            </tr>
                        @endforeach

                        <!-- <tfoot> -->
                        <tr>
                            <th colspan="3" style="text-align:left">{{trans('file.Total')}}</th>
                            <th style="text-align:right">{{number_format((float)$lims_sale_data->total_price, 2)}}</th>
                        </tr>
                        @if($general_setting->invoice_format == 'gst' && $general_setting->state == 1)
                            <tr>
                                <td colspan="3">IGST</td>
                                <td style="text-align:right">{{number_format((float)$total_product_tax, 2)}}</td>
                            </tr>
                        @elseif($general_setting->invoice_format == 'gst' && $general_setting->state == 2)
                            <tr>
                                <td colspan="3">SGST</td>
                                <td style="text-align:right">{{number_format((float)($total_product_tax / 2), 2)}}</td>
                            </tr>
                            <tr>
                                <td colspan="3">CGST</td>
                                <td style="text-align:right">{{number_format((float)($total_product_tax / 2), 2)}}</td>
                            </tr>
                        @endif
                        @if($lims_sale_data->order_tax)
                            <tr>
                                <th colspan="3" style="text-align:left">{{trans('file.Order Tax')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->order_tax, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->order_discount)
                            <tr>
                                <th colspan="3" style="text-align:left">{{trans('file.Order Discount')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->order_discount, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->coupon_discount)
                            <tr>
                                <th colspan="3" style="text-align:left">{{trans('file.Coupon Discount')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->coupon_discount, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->shipping_cost)
                            <tr>
                                <th colspan="3" style="text-align:left">{{trans('file.Shipping Cost')}}</th>
                                <th style="text-align:right">{{number_format((float)$lims_sale_data->shipping_cost, 2)}}</th>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="3" style="text-align:left">{{trans('file.grand total')}}</th>
                            <th style="text-align:right">{{number_format((float)$lims_sale_data->grand_total, 2)}}</th>
                        </tr>
                        <tr>
                            <th colspan="2" style="text-align:left">Payment Status</th>
                            <th></th>
                            <td style="text-align:right">
                                @if($lims_sale_data->payment_status == 1)
                                    {{ trans('file.Pending') }}
                                @elseif($lims_sale_data->payment_status == 2)
                                    {{ trans('file.Due') }}
                                @elseif($lims_sale_data->payment_status == 3)
                                    {{ trans('file.Partial') }}
                                @else
                                    {{ trans('file.Paid') }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2" style="text-align:left">Booking Status</th>
                            <th></th>
                            <td style="text-align:right">
                                @if ($lims_sale_data->booking_status == 1)
                                    <span class="badge badge-success">Complete</span>
                                @elseif ($lims_sale_data->booking_status == 2)
                                    <span class="badge badge-warning">Pending</span>
                                @elseif ($lims_sale_data->booking_status == 3)
                                    <span class="badge badge-info">Return</span>
                                @elseif ($lims_sale_data->booking_status == 4)
                                    <span class="badge badge-info">Partial Return</span>
                                @else
                                    <span class="badge badge-danger">Draft</span>
                                @endif
                            </td>
                        </tr>
                        @if($lims_sale_data->booking_note)
                            <tr>
                                <th style="text-align:left">Booking Note</th>
                                <td colspan="3">{!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->booking_note) !!}</td>
                            </tr>
                        @endif
                        @if($lims_sale_data->staff_note)
                            <tr>
                                <th style="text-align:left">Staff Note</th>
                                <td colspan="3">{!! $lims_sale_data->staff_note !!}</td>
                            </tr>
                        @endif
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
                    <table>
                        <tbody>
                        @foreach($lims_payment_data as $payment_data)
                            <tr style="background-color:#ddd;">
                                <td style="padding: 5px;width:30%">{{trans('file.Paid By')}}: {{$payment_data->paying_method}}</td>
                                <td style="padding: 5px;width:40%">{{trans('file.Amount')}}: {{number_format((float)$payment_data->amount, 2)}}</td>
                                <td style="padding: 5px;width:30%">{{trans('file.Change')}}: {{number_format((float)$payment_data->change, 2)}}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if($general_setting->invoice_format == 'beyond_a4')
                <div class="lastPage" >
                    <img id="print-footer" src="{{url('public/logo', $footer)}}" style=" width: 100%;">
                </div>
            @endif

</body>
</html>

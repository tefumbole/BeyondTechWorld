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
        td,th {padding: 7px 0;text-align: left}

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
    <img src="{{public_path('logo/') . $header}}" style=" width: 100%;">
{{--    <img src="{{public_path('logo/') . $water_mark}}" class="waterm-mark">--}}
    <div style="max-width:95vw;margin:0 auto; ">
        @else
    <div style="max-width:800px;margin:0 auto; ">
        @endif

                <div id="receipt-data">
                    @if($general_setting->invoice_format != 'beyond_a4')
                    <div class="centered">
                        @if($general_setting->site_logo)
                            <img src="{{public_path('logo/') . $general_setting->site_logo}}" height="42" width="50" style="margin:10px 0;filter: brightness(0);">
                        @endif

                        <p>{{trans('file.Address')}}: {{$lims_warehouse_data->address}}
                            <br>{{trans('file.Phone Number')}}: {{$lims_warehouse_data->phone}}
                        </p>
                    </div>
                    @endif
                        <div class="col-md-12">
                            <center><h3>Quotation Details</h3></center>
                        </div>
                    <p>{{trans('file.Date')}}: {{$lims_sale_data->created_at->format('D, M d, Y h:i:s')}}<br>
                        {{trans('file.reference')}}: {{$lims_sale_data->reference_no}}<br>
                        {{trans('file.customer')}}: {{$lims_customer_data->name}}<br>
                    </p>
                        <table>
                            <tr>
                                <td>
                                    <span style="font-weight: bold">From:</span><br>
                                    {{@$lims_sale_data->biller->name}}<br>
                                    {{@$lims_sale_data->biller->company_name}}<br>
                                    {{@$lims_sale_data->biller->email}}<br>
                                    {{@$lims_sale_data->biller->phone_number}}<br>
                                    {{@$lims_sale_data->biller->address}}<br>
                                </td>
                                <td>
                                    <span style="font-weight: bold">To:</span><br>
                                    {{$lims_customer_data->name}}<br>
                                    {{$lims_customer_data->phone_number}}<br>
                                    {{$lims_customer_data->email}}<br>
                                    {{$lims_customer_data->address}}<br>
                            </tr>
                        </table>
                    <table class="table-data">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Batch No</th>
                            <th>QTY</th>
                            <th>Unit Price</th>
                            <th>Tax</th>
                            <th>Discount</th>
                            <th>Sub Total</th>
                        </tr>
                        </thead>
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
                                        $product_name = $lims_product_data->name;
                                        $product_batch_name = $product_batch_data->batch_no;
                                    } else {
                                        $product_name = $lims_product_data->name;

                                        foreach ($multi_product_batch_id as $key => $batch_id) {
                                            $product_batch_data = \App\ProductBatch::select('batch_no')->find($batch_id);
                                            $product_name = $lims_product_data->name;
                                            $product_batch_name = $product_batch_data->batch_no;
                                        }
                                    }
                                }
                                else
                                    $product_name = $lims_product_data->name;
                                ?>
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$product_name}}</td>
                                <td>{{ $product_batch_name ?? 'NAN' }}</td>
                                <td>{{ $product_sale_data->qty }}</td>
                                <td>{{ $product_sale_data->net_unit_price }}</td>
                                <td>{{ $product_sale_data->tax }}</td>
                                <td>{{ $product_sale_data->discount }}</td>
                                <td >{{number_format((float)$product_sale_data->total, 2)}}</td>
                            </tr>
                        @endforeach

                        <!-- <tfoot> -->
                        <tr>
                            <th colspan="7" style="text-align:left">{{trans('file.Total')}}</th>
                            <th  >{{number_format((float)$lims_sale_data->total_price, 2)}}</th>
                        </tr>
                        @if($general_setting->invoice_format == 'gst' && $general_setting->state == 1)
                            <tr>
                                <td colspan="7">IGST</td>
                                <td  >{{number_format((float)$total_product_tax, 2)}}</td>
                            </tr>
                        @elseif($general_setting->invoice_format == 'gst' && $general_setting->state == 2)
                            <tr>
                                <td colspan="7">SGST</td>
                                <td  >{{number_format((float)($total_product_tax / 2), 2)}}</td>
                            </tr>
                            <tr>
                                <td colspan="7">CGST</td>
                                <td  >{{number_format((float)($total_product_tax / 2), 2)}}</td>
                            </tr>
                        @endif
                        @if($lims_sale_data->order_tax)
                            <tr>
                                <th colspan="2" style="text-align:left">{{trans('file.Order Tax')}}</th>
                                <th  >{{number_format((float)$lims_sale_data->order_tax, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->order_discount)
                            <tr>
                                <th colspan="7" style="text-align:left">{{trans('file.Order Discount')}}</th>
                                <th  >{{number_format((float)$lims_sale_data->order_discount, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->coupon_discount)
                            <tr>
                                <th colspan="7" style="text-align:left">{{trans('file.Coupon Discount')}}</th>
                                <th  >{{number_format((float)$lims_sale_data->coupon_discount, 2)}}</th>
                            </tr>
                        @endif
                        @if($lims_sale_data->shipping_cost)
                            <tr>
                                <th colspan="7" style="text-align:left">{{trans('file.Shipping Cost')}}</th>
                                <th  >{{number_format((float)$lims_sale_data->shipping_cost, 2)}}</th>
                            </tr>
                        @endif
                        <tr>
                            <th colspan="7" style="text-align:left">{{trans('file.grand total')}}</th>
                            <th  >{{number_format((float)$lims_sale_data->grand_total, 2)}}</th>
                        </tr>
                        <tr>
                            @if($general_setting->currency_position == 'prefix')
                                <th class="centered" colspan="8">{{trans('file.In Words')}}: <span>{{$currency->code}}</span> <span>{{str_replace("-"," ",$numberInWords)}}</span></th>
                            @else
                                <th class="centered" colspan="8">{{trans('file.In Words')}}: <span>{{str_replace("-"," ",$numberInWords)}}</span> <span>{{$currency->code}}</span></th>
                            @endif
                        </tr>
                        </tbody>
                        <!-- </tfoot> -->
                    </table>
                        <div style="text-align:left">
                            <span style="font-weight: bold">{{trans('file.Note')}}:</span>
                            {!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->note) !!}
                        </div>
                        <div style="text-align:left">
                            <span style="font-weight: bold">{{trans('file.Created By')}}:</span>
                            {{ @$lims_sale_data->user->name }} |
                            {{ @$lims_sale_data->user->phone }}
                        </div>
                    <!-- <div class="centered" style="margin:30px 0 50px">
            <small>{{trans('file.Invoice Generated By')}} {{$general_setting->site_title}}.
            {{trans('file.Developed By')}} Faby Developers</strong></small>
        </div> -->
                </div>
            </div>
            @if($general_setting->invoice_format == 'beyond_a4')
                <div class="lastPage" >
                    <img id="print-footer" src="{{public_path('logo/') . $footer}}" style=" width: 100%;">
                </div>
            @endif

</body>
</html>

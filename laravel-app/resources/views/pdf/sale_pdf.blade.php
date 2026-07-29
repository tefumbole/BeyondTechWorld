<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $general_setting->site_title }}</title>
    @include('pdf.partials._invoice_styles')
    <style type="text/css">
        .inv-title { margin-bottom: 6px; }
        .inv-ref { margin-bottom: 6px; }
        table.inv-meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.inv-meta td {
            width: 50%;
            vertical-align: top;
            padding: 6px 8px;
            border: 1px solid #dfe3ec;
            background: #f8f9fc;
            font-size: 10px;
            line-height: 1.35;
        }
        table.inv-meta .inv-label {
            display: block;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6449e7;
            margin-bottom: 3px;
        }
        table.inv-meta .inv-name { font-weight: bold; }
        table.inv-items thead th,
        table.inv-items tbody td { padding: 4px 5px; }
        table.inv-summary { margin-top: 6px; }
        .inv-box { padding: 5px 7px; margin-bottom: 5px; }
        .inv-notes { margin-top: 6px; font-size: 10px; page-break-inside: avoid; }
        .inv-notes p { margin: 0 0 3px; }
    </style>
</head>
<body>
@include('pdf.partials._invoice_open')

@php
    $saleStatus = $lims_sale_data->sale_status == 1
        ? trans('file.Completed')
        : ($lims_sale_data->sale_status == 2 ? trans('file.Pending') : trans('file.Draft'));
    $orderTax = (float) ($lims_sale_data->order_tax ?? 0);
    $orderDiscount = (float) ($lims_sale_data->order_discount ?? 0);
    $couponDiscount = (float) ($lims_sale_data->coupon_discount ?? 0);
    $shippingCost = (float) ($lims_sale_data->shipping_cost ?? 0);
    $paidAmount = (float) ($lims_sale_data->paid_amount ?? 0);
    $dueAmount = (float) $lims_sale_data->grand_total - $paidAmount;
    $saleNote = trim(strip_tags((string) ($lims_sale_data->sale_note ?? '')));
    $staffNote = trim(strip_tags((string) ($lims_sale_data->staff_note ?? '')));
@endphp

<div class="inv-title">{{ trans('file.Sale') }} {{ trans('file.Invoice') }}</div>
<div class="inv-ref">
    {{ $lims_sale_data->reference_no }}
    &nbsp;&middot;&nbsp; {{ $lims_sale_data->created_at->format('D, M d, Y H:i') }}
</div>

<table class="inv-meta">
    <tr>
        <td>
            <strong>{{ trans('file.Date') }}:</strong> {{ $lims_sale_data->created_at->format('d-m-Y') }}<br>
            <strong>{{ trans('file.reference') }}:</strong> {{ $lims_sale_data->reference_no }}<br>
            @if(@$lims_warehouse_data->name)
                <strong>{{ trans('file.Warehouse') }}:</strong> {{ $lims_warehouse_data->name }}<br>
            @endif
            <strong>{{ trans('file.Sale Status') }}:</strong> {{ $saleStatus }}
        </td>
        <td>
            <span class="inv-label">{{ trans('file.To') }}</span>
            <span class="inv-name">{{ @$lims_customer_data->name }}</span><br>
            @if(@$lims_customer_data->phone_number){{ $lims_customer_data->phone_number }}<br>@endif
            @if(@$lims_customer_data->email){{ $lims_customer_data->email }}<br>@endif
            @if(@$lims_customer_data->address){{ $lims_customer_data->address }}@endif
            @if(@$lims_customer_data->city){{ @$lims_customer_data->address ? ', ' : '' }}{{ $lims_customer_data->city }}@endif
        </td>
    </tr>
</table>

<table class="inv-items">
    <colgroup>
        <col style="width:4%"><col style="width:42%"><col style="width:8%">
        <col style="width:15%"><col style="width:12%"><col style="width:19%">
    </colgroup>
    <thead>
    <tr>
        <th class="inv-num">#</th>
        <th>{{ trans('file.product') }}</th>
        <th class="inv-qty">{{ trans('file.qty') }}</th>
        <th class="inv-money">{{ trans('file.Unit Price') }}</th>
        <th class="inv-money">{{ trans('file.Tax') }}</th>
        <th class="inv-money">Sub Total</th>
    </tr>
    </thead>
    <tbody>
    <?php $total_product_tax = 0; ?>
    @foreach($lims_product_sale_data as $key => $product_sale_data)
        <?php
        $multi_product_batch_id = null;
        $multi_product_batch_qty = null;
        if ($product_sale_data->multi_product_batch_id != null) {
            $multi_product_batch_id = json_decode($product_sale_data->multi_product_batch_id);
            $multi_product_batch_qty = json_decode($product_sale_data->multi_product_batch_qty);
        }
        $lims_product_data = \App\Product::find($product_sale_data->product_id);
        $batch_note = null;
        if ($product_sale_data->variant_id) {
            $variant_data = \App\Variant::find($product_sale_data->variant_id);
            $product_name = $lims_product_data->name.' ['.@$variant_data->name.']';
        } elseif ($product_sale_data->product_batch_id) {
            $product_name = $lims_product_data->name;
            if (! $multi_product_batch_id) {
                $product_batch_data = \App\ProductBatch::select('batch_no')->find($product_sale_data->product_batch_id);
                $batch_note = trans('file.Batch No').': '.@$product_batch_data->batch_no;
            } else {
                $batches = [];
                foreach ($multi_product_batch_id as $i => $batch_id) {
                    $product_batch_data = \App\ProductBatch::select('batch_no')->find($batch_id);
                    $batches[] = @$product_batch_data->batch_no.' × '.$multi_product_batch_qty[$i];
                }
                $batch_note = trans('file.Batch No').': '.implode(', ', $batches);
            }
        } else {
            $product_name = $lims_product_data->name;
        }
        if ($product_sale_data->tax_rate) {
            $total_product_tax += $product_sale_data->tax;
        }
        $unit_price = $product_sale_data->qty ? $product_sale_data->total / $product_sale_data->qty : 0;
        ?>
        <tr>
            <td class="inv-num">{{ $key + 1 }}</td>
            <td>
                {{ $product_name }}
                @if($batch_note)<span class="inv-sub">{{ $batch_note }}</span>@endif
            </td>
            <td class="inv-qty">{{ $product_sale_data->qty + 0 }}</td>
            <td class="inv-money">{{ number_format((float) $unit_price, 2) }}</td>
            <td class="inv-money">
                @if($product_sale_data->tax_rate)
                    {{ number_format((float) $product_sale_data->tax, 2) }}
                    <span class="inv-sub">{{ $product_sale_data->tax_rate }}%</span>
                @else
                    &mdash;
                @endif
            </td>
            <td class="inv-money">{{ number_format((float) $product_sale_data->total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="inv-summary">
    <tr>
        <td class="inv-summary-left">
            <div class="inv-box">
                <span class="inv-label">{{ trans('file.In Words') }}</span>
                <span class="inv-words">
                    @if($general_setting->currency_position == 'prefix')
                        {{ $currency->code }} {{ str_replace('-', ' ', $numberInWords) }}
                    @else
                        {{ str_replace('-', ' ', $numberInWords) }} {{ $currency->code }}
                    @endif
                </span>
            </div>
            @if(count($lims_payment_data))
                <div class="inv-box">
                    <span class="inv-label">{{ trans('file.Payment') }}</span>
                    @foreach($lims_payment_data as $payment_data)
                        {{ $payment_data->paying_method }}:
                        {{ number_format((float) $payment_data->amount, 2) }}
                        @if($payment_data->change > 0)
                            ({{ trans('file.Change') }}: {{ number_format((float) $payment_data->change, 2) }})
                        @endif
                        <br>
                    @endforeach
                </div>
            @endif
            @if($saleNote !== '')
                <div class="inv-box inv-note">
                    <span class="inv-label">{{ trans('file.Sale Note') }}</span>
                    {!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->sale_note) !!}
                </div>
            @endif
            @if($staffNote !== '')
                <div class="inv-box inv-note">
                    <span class="inv-label">{{ trans('file.Staff Note') }}</span>
                    {!! $lims_sale_data->staff_note !!}
                </div>
            @endif
            <table class="inv-foot-row">
                <tr>
                    <td class="inv-thanks">{{ trans('file.Thank you for shopping with us. Please come again') }}</td>
                    <td class="inv-codes">
                        <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128').'" height="22" width="112" alt="">'; ?>
                        &nbsp;
                        <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($lims_sale_data->reference_no, 'QRCODE').'" height="38" width="38" alt="">'; ?>
                    </td>
                </tr>
            </table>
        </td>
        <td class="inv-summary-right">
            <table class="inv-totals">
                <tr>
                    <th>{{ trans('file.Total') }}</th>
                    <td>{{ number_format((float) $lims_sale_data->total_price, 2) }}</td>
                </tr>
                @if($general_setting->invoice_format == 'gst' && $general_setting->state == 1)
                    <tr>
                        <th>IGST</th>
                        <td>{{ number_format((float) $total_product_tax, 2) }}</td>
                    </tr>
                @elseif($general_setting->invoice_format == 'gst' && $general_setting->state == 2)
                    <tr>
                        <th>SGST</th>
                        <td>{{ number_format((float) ($total_product_tax / 2), 2) }}</td>
                    </tr>
                    <tr>
                        <th>CGST</th>
                        <td>{{ number_format((float) ($total_product_tax / 2), 2) }}</td>
                    </tr>
                @endif
                @if($orderTax > 0)
                    <tr>
                        <th>{{ trans('file.Order Tax') }}</th>
                        <td>{{ number_format($orderTax, 2) }}</td>
                    </tr>
                @endif
                @if($orderDiscount > 0)
                    <tr>
                        <th>{{ trans('file.Order Discount') }}</th>
                        <td>{{ number_format($orderDiscount, 2) }}</td>
                    </tr>
                @endif
                @if($couponDiscount > 0)
                    <tr>
                        <th>{{ trans('file.Coupon Discount') }}</th>
                        <td>{{ number_format($couponDiscount, 2) }}</td>
                    </tr>
                @endif
                @if($shippingCost > 0)
                    <tr>
                        <th>{{ trans('file.Shipping Cost') }}</th>
                        <td>{{ number_format($shippingCost, 2) }}</td>
                    </tr>
                @endif
                <tr class="inv-grand">
                    <th>{{ trans('file.grand total') }}</th>
                    <td>{{ number_format((float) $lims_sale_data->grand_total, 2) }}</td>
                </tr>
                <tr>
                    <th>{{ trans('file.Paid Amount') }}</th>
                    <td>{{ number_format($paidAmount, 2) }}</td>
                </tr>
                @if($dueAmount > 0.0001)
                    <tr>
                        <th>{{ trans('file.Due') }}</th>
                        <td>{{ number_format($dueAmount, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <th>{{ trans('file.Payment Status') }}</th>
                    <td>
                        <span class="inv-status">
                            @if($lims_sale_data->payment_status == 1)
                                {{ trans('file.Pending') }}
                            @elseif($lims_sale_data->payment_status == 2)
                                {{ trans('file.Due') }}
                            @elseif($lims_sale_data->payment_status == 3)
                                {{ trans('file.Partial') }}
                            @else
                                {{ trans('file.Paid') }}
                            @endif
                        </span>
                    </td>
                </tr>
            </table>
            @if(@$lims_sale_data->user)
                <div class="inv-notes">
                    <strong>{{ trans('file.Created By') }}:</strong>
                    {{ $lims_sale_data->user->name }}
                    @if(@$lims_sale_data->user->email)<br>{{ $lims_sale_data->user->email }}@endif
                </div>
            @endif
        </td>
    </tr>
</table>

@include('pdf.partials._invoice_close')
</body>
</html>

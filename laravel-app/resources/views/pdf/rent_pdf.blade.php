<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $general_setting->site_title }}</title>
    @include('pdf.partials._invoice_styles')
</head>
<body>
@include('pdf.partials._invoice_open')

@php
    // bookings stores the reference in reference_no; the old `reference` attribute
    // does not exist on the table and always rendered blank.
    $invoiceReference = $lims_sale_data->reference_no ?: ($lims_sale_data->reference ?? '');
@endphp

<div class="inv-title">Rental Invoice</div>
<div class="inv-ref">
    {{ $invoiceReference }}
    &nbsp;&middot;&nbsp; {{ $lims_sale_data->created_at->format('D, M d, Y H:i') }}
</div>

<table class="inv-parties">
    <tr>
        <td>
            <span class="inv-label">{{ trans('file.From') }}</span>
            <span class="inv-name">{{ $general_setting->site_title }}</span><br>
            {{ @$lims_sale_data->biller->address }}<br>
            {{ @$lims_sale_data->biller->email }}<br>
            {{ @$lims_sale_data->biller->phone_number }}
        </td>
        <td>
            <span class="inv-label">{{ trans('file.customer') }}</span>
            <span class="inv-name">{{ @$lims_customer_data->name }}</span><br>
            @if(@$lims_customer_data->phone_number){{ $lims_customer_data->phone_number }}<br>@endif
            @if(@$lims_customer_data->email){{ $lims_customer_data->email }}<br>@endif
            {{ @$lims_customer_data->address }}
        </td>
    </tr>
</table>

<table class="inv-items">
    <colgroup>
        <col style="width:4%"><col style="width:32%"><col style="width:24%">
        <col style="width:7%"><col style="width:15%"><col style="width:18%">
    </colgroup>
    <thead>
    <tr>
        <th class="inv-num">#</th>
        <th>{{ trans('file.product') }}</th>
        <th>Period</th>
        <th class="inv-qty">{{ trans('file.qty') }}</th>
        <th class="inv-money">{{ trans('file.Unit Price') }}</th>
        <th class="inv-money">Sub Total</th>
    </tr>
    </thead>
    <tbody>
    <?php $total_product_tax = 0; ?>
    @foreach($lims_product_sale_data as $key => $product_sale_data)
        <?php $lims_product_data = \App\Product::find($product_sale_data->product_id); ?>
        <tr>
            <td class="inv-num">{{ $key + 1 }}</td>
            <td>{{ @$lims_product_data->name }}</td>
            <td>
                {{ date('d M Y, H:i', strtotime($product_sale_data->start)) }}
                <span class="inv-sub">to {{ date('d M Y, H:i', strtotime($product_sale_data->end)) }}</span>
            </td>
            <td class="inv-qty">{{ $product_sale_data->qty + 0 }}</td>
            <td class="inv-money">{{ number_format((float) $product_sale_data->net_unit_price, 2) }}</td>
            <td class="inv-money">{{ number_format((float) $product_sale_data->net_unit_price * $product_sale_data->qty, 2) }}</td>
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
            <table class="inv-foot-row">
                <tr>
                    <td class="inv-thanks">{{ trans('file.Thank you for shopping with us. Please come again') }}</td>
                    <td class="inv-codes">
                        @if($invoiceReference)
                            <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($invoiceReference, 'QRCODE').'" height="38" width="38" alt="">'; ?>
                        @endif
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
                @if($lims_sale_data->order_tax)
                    <tr>
                        <th>{{ trans('file.Order Tax') }}</th>
                        <td>{{ number_format((float) $lims_sale_data->order_tax, 2) }}</td>
                    </tr>
                @endif
                @if($lims_sale_data->order_discount)
                    <tr>
                        <th>{{ trans('file.Order Discount') }}</th>
                        <td>{{ number_format((float) $lims_sale_data->order_discount, 2) }}</td>
                    </tr>
                @endif
                @if($lims_sale_data->coupon_discount)
                    <tr>
                        <th>{{ trans('file.Coupon Discount') }}</th>
                        <td>{{ number_format((float) $lims_sale_data->coupon_discount, 2) }}</td>
                    </tr>
                @endif
                @if($lims_sale_data->shipping_cost)
                    <tr>
                        <th>{{ trans('file.Shipping Cost') }}</th>
                        <td>{{ number_format((float) $lims_sale_data->shipping_cost, 2) }}</td>
                    </tr>
                @endif
                <tr class="inv-grand">
                    <th>{{ trans('file.grand total') }}</th>
                    <td>{{ number_format((float) $lims_sale_data->grand_total, 2) }}</td>
                </tr>
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
                <tr>
                    <th>Order Status</th>
                    <td>
                        <span class="inv-status">
                            @if($lims_sale_data->order_status == 1)
                                Complete
                            @elseif($lims_sale_data->order_status == 2)
                                Rejected
                            @else
                                {{ trans('file.Pending') }}
                            @endif
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>


@include('pdf.partials._invoice_close')
</body>
</html>

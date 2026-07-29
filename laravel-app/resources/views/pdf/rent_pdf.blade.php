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
    $invoiceReference = $lims_sale_data->reference_no ?: ($lims_sale_data->reference ?? '');
    $orderTax = (float) ($lims_sale_data->order_tax ?? 0);
    $orderDiscount = (float) ($lims_sale_data->order_discount ?? 0);
    $couponDiscount = (float) ($lims_sale_data->coupon_discount ?? 0);
    $shippingCost = (float) ($lims_sale_data->shipping_cost ?? 0);
    $paidAmount = (float) ($lims_sale_data->paid_amount ?? 0);
    $dueAmount = max(0, (float) $lims_sale_data->grand_total - $paidAmount);
    $statusCode = (int) ($lims_sale_data->booking_status ?? $lims_sale_data->order_status ?? 0);
    $orderStatus = trans('file.Pending');
    if ($statusCode === 1) {
        $orderStatus = 'Complete';
    } elseif ($statusCode === 2) {
        $orderStatus = trans('file.Pending');
    } elseif ($statusCode === 3) {
        $orderStatus = 'Return';
    } elseif ($statusCode === 4) {
        $orderStatus = 'Partial Return';
    }
    $earliestStart = null;
    $latestEnd = null;
    foreach ($lims_product_sale_data as $row) {
        if (! empty($row->start)) {
            $ts = strtotime($row->start);
            if ($ts && ($earliestStart === null || $ts < $earliestStart)) {
                $earliestStart = $ts;
            }
        }
        if (! empty($row->end)) {
            $ts = strtotime($row->end);
            if ($ts && ($latestEnd === null || $ts > $latestEnd)) {
                $latestEnd = $ts;
            }
        }
    }
@endphp

<div class="inv-title">Rental Invoice</div>
<div class="inv-ref">
    <strong>{{ trans('file.reference') }}:</strong> {{ $invoiceReference }}<br>
    <strong>{{ trans('file.Date') }}:</strong> {{ $lims_sale_data->created_at->format('d-m-Y') }}
</div>

<table class="inv-meta">
    <tr>
        <td>
            <strong>{{ trans('file.reference') }}:</strong> {{ $invoiceReference }}<br>
            <strong>{{ trans('file.Date') }}:</strong> {{ $lims_sale_data->created_at->format('d-m-Y') }}<br>
            @if(@$lims_warehouse_data->name)
                <strong>{{ trans('file.Warehouse') }}:</strong> {{ $lims_warehouse_data->name }}<br>
            @elseif(@$lims_sale_data->warehouse->name)
                <strong>{{ trans('file.Warehouse') }}:</strong> {{ $lims_sale_data->warehouse->name }}<br>
            @endif
            <strong>Order Status:</strong> {{ $orderStatus }}
        </td>
        <td>
            <span class="inv-label">{{ trans('file.To') }}</span>
            <span class="inv-name">{{ @$lims_customer_data->name }}</span><br>
            @php
                $customerPhone = $lims_customer_data->phone_number ?? ($lims_customer_data->phone ?? null);
                $customerAddress = $lims_customer_data->address ?? null;
                $customerCity = $lims_customer_data->city ?? null;
            @endphp
            @if($customerPhone){{ $customerPhone }}<br>@endif
            @if(@$lims_customer_data->email){{ $lims_customer_data->email }}<br>@endif
            @if($customerAddress){{ $customerAddress }}@endif
            @if($customerCity){{ $customerAddress ? ', ' : '' }}{{ $customerCity }}@endif
        </td>
    </tr>
</table>

@if($earliestStart || $latestEnd)
    <div class="inv-schedule">
        <strong>Rental Start Date:</strong>
        {{ $earliestStart ? date('d-m-Y H:i', $earliestStart) : '—' }}
        <br>
        <strong>Expected Return Date:</strong>
        {{ $latestEnd ? date('d-m-Y H:i', $latestEnd) : '—' }}
    </div>
@endif

<table class="inv-items">
    <colgroup>
        <col style="width:4%"><col style="width:30%"><col style="width:28%">
        <col style="width:7%"><col style="width:14%"><col style="width:17%">
    </colgroup>
    <thead>
    <tr>
        <th class="inv-num">#</th>
        <th>{{ trans('file.product') }}</th>
        <th>Start / Return</th>
        <th class="inv-qty">{{ trans('file.qty') }}</th>
        <th class="inv-money">{{ trans('file.Unit Price') }}</th>
        <th class="inv-money">Sub Total</th>
    </tr>
    </thead>
    <tbody>
    <?php $total_product_tax = 0; ?>
    @foreach($lims_product_sale_data as $key => $product_sale_data)
        <?php $lims_product_data = \App\Product::find($product_sale_data->product_id); ?>
        <tr class="{{ $key % 2 === 1 ? 'inv-alt' : '' }}">
            <td class="inv-num">{{ $key + 1 }}</td>
            <td>{{ @$lims_product_data->name }}</td>
            <td>
                <strong>Start:</strong> {{ $product_sale_data->start ? date('d-m-Y H:i', strtotime($product_sale_data->start)) : '—' }}
                <span class="inv-sub"><strong>Return:</strong> {{ $product_sale_data->end ? date('d-m-Y H:i', strtotime($product_sale_data->end)) : '—' }}</span>
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
            <div class="inv-thanks" style="margin-top:6px;width:100%;">{{ trans('file.Thank you for shopping with us. Please come again') }}</div>
        </td>
        <td class="inv-summary-right">
            <table class="inv-totals">
                <tr>
                    <th>{{ trans('file.Total') }}</th>
                    <td>{{ number_format((float) $lims_sale_data->total_price, 2) }}</td>
                </tr>
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
                    <th>Amount Paid</th>
                    <td>{{ number_format($paidAmount, 2) }}</td>
                </tr>
                <tr>
                    <th>Amount Pending</th>
                    <td>{{ number_format($dueAmount, 2) }}</td>
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
                    <td><span class="inv-status">{{ $orderStatus }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="inv-codes-block">
    @if(@$lims_sale_data->user)
        <div class="inv-created">
            <strong>{{ trans('file.Created By') }}:</strong> {{ $lims_sale_data->user->name }}
            @if(@$lims_sale_data->user->email)<br>{{ $lims_sale_data->user->email }}@endif
        </div>
    @endif
    @if($invoiceReference)
        <div class="inv-qr" style="margin:0 0 6px;">
            <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($invoiceReference, 'QRCODE').'" height="52" width="52" alt="qrcode">'; ?>
        </div>
        <div class="inv-barcode">
            <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($invoiceReference, 'C128').'" height="24" width="160" alt="barcode">'; ?>
        </div>
    @endif
</div>

@include('pdf.partials._invoice_close')
</body>
</html>

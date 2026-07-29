<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $general_setting->site_title }}</title>
    @include('pdf.partials._invoice_styles')
    <style type="text/css">
        .inv-title { margin-bottom: 4px; }
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
            color: #1f2a44;
            margin-bottom: 3px;
        }
        table.inv-meta .inv-name { font-weight: bold; }
        table.inv-items { font-size: 9.5px; }
        table.inv-items thead th,
        table.inv-items tbody td { padding: 3px 4px; }
        table.inv-summary { margin-top: 6px; }
        .inv-box { padding: 5px 7px; margin-bottom: 5px; }
        .inv-codes-block { margin-top: 8px; page-break-inside: avoid; text-align: left; }
        .inv-codes-block .inv-created { font-size: 10px; line-height: 1.4; margin-bottom: 6px; text-align: left; }
        .inv-codes-block .inv-qr,
        .inv-codes-block .inv-barcode { text-align: center; }
        .inv-codes-block img { display: block; margin: 0 auto; }
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
    $dueAmount = max(0, (float) $lims_sale_data->grand_total - $paidAmount);
    $saleNote = trim(strip_tags((string) ($lims_sale_data->sale_note ?? '')));
    $staffNote = trim(strip_tags((string) ($lims_sale_data->staff_note ?? '')));
    $totalProductTax = 0;
    $totalProductDiscount = 0;
    $totalProductSubtotal = 0;
    $invoiceQrUrl = \App\Support\SaleInvoiceQr::scanUrl($lims_sale_data);
@endphp

<div class="inv-title">Sales Invoice</div>
<div class="inv-ref">
    <strong>{{ trans('file.reference') }}:</strong> {{ $lims_sale_data->reference_no }}<br>
    <strong>{{ trans('file.Date') }}:</strong> {{ $lims_sale_data->created_at->format('d-m-Y') }}
</div>

<table class="inv-meta">
    <tr>
        <td>
            <strong>{{ trans('file.reference') }}:</strong> {{ $lims_sale_data->reference_no }}<br>
            <strong>{{ trans('file.Date') }}:</strong> {{ $lims_sale_data->created_at->format('d-m-Y') }}<br>
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
        <col style="width:5%">
        <col style="width:40%">
        <col style="width:8%">
        <col style="width:13%">
        <col style="width:12%">
        <col style="width:10%">
        <col style="width:12%">
    </colgroup>
    <thead>
    <tr>
        <th class="inv-num">#</th>
        <th>{{ trans('file.product') }}</th>
        <th class="inv-qty">{{ trans('file.Qty') }}</th>
        <th class="inv-money">{{ trans('file.Unit Price') }}</th>
        <th class="inv-money">{{ trans('file.Tax') }}</th>
        <th class="inv-money">{{ trans('file.Discount') }}</th>
        <th class="inv-money">{{ trans('file.Subtotal') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lims_product_sale_data as $key => $product_sale_data)
        <?php
        $lims_product_data = \App\Product::find($product_sale_data->product_id);
        $productCode = $lims_product_data->code ?? '';
        if ($product_sale_data->variant_id) {
            $variant_data = \App\Variant::find($product_sale_data->variant_id);
            $lims_product_variant_data = \App\ProductVariant::select('item_code')
                ->FindExactProduct($product_sale_data->product_id, $product_sale_data->variant_id)
                ->first();
            if ($lims_product_variant_data) {
                $productCode = $lims_product_variant_data->item_code;
            }
            $product_name = $lims_product_data->name.' ['.@$variant_data->name.']';
            if ($productCode) {
                $product_name .= ' ['.$productCode.']';
            }
        } else {
            $product_name = $lims_product_data->name.($productCode ? ' ['.$productCode.']' : '');
        }

        $lineTax = (float) ($product_sale_data->tax ?? 0);
        $lineDiscount = (float) ($product_sale_data->discount ?? 0);
        $lineTotal = (float) ($product_sale_data->total ?? 0);
        $qty = (float) ($product_sale_data->qty ?: 0);
        $unitPrice = $qty ? ($lineTotal / $qty) : 0;
        $totalProductTax += $lineTax;
        $totalProductDiscount += $lineDiscount;
        $totalProductSubtotal += $lineTotal;
        $rowClass = ($key % 2 === 1) ? 'inv-alt' : '';
        ?>
        <tr class="{{ $rowClass }}">
            <td class="inv-num">{{ $key + 1 }}</td>
            <td>{{ $product_name }}</td>
            <td class="inv-qty">{{ $product_sale_data->qty + 0 }}</td>
            <td class="inv-money">{{ number_format($unitPrice, 2) }}</td>
            <td class="inv-money">{{ number_format($lineTax, 2) }}({{ $product_sale_data->tax_rate + 0 }}%)</td>
            <td class="inv-money">{{ number_format($lineDiscount, 2) }}</td>
            <td class="inv-money">{{ number_format($lineTotal, 2) }}</td>
        </tr>
    @endforeach
    <tr class="inv-total">
        <td colspan="4" style="text-align:right;"><strong>{{ trans('file.Total') }}:</strong></td>
        <td class="inv-money">{{ number_format($totalProductTax, 2) }}</td>
        <td class="inv-money">{{ number_format($totalProductDiscount, 2) }}</td>
        <td class="inv-money">{{ number_format($totalProductSubtotal, 2) }}</td>
    </tr>
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
            <div class="inv-thanks" style="margin-top:6px;width:100%;">{{ trans('file.Thank you for shopping with us. Please come again') }}</div>
        </td>
        <td class="inv-summary-right">
            <table class="inv-totals">
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
    <div class="inv-qr" style="margin:0 0 6px;">
        <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($invoiceQrUrl, 'QRCODE').'" height="52" width="52" alt="qrcode">'; ?>
    </div>
    <div class="inv-barcode">
        <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128').'" height="24" width="160" alt="barcode">'; ?>
    </div>
</div>

@include('pdf.partials._invoice_close')
</body>
</html>

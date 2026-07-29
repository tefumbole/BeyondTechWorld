<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
    <title>{{$general_setting->site_title}} — {{ $lims_sale_data->reference_no }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $isBeyond = ($general_setting->invoice_format ?? '') === 'beyond_a4';
        $letterhead = \App\Support\Letterhead::ensureSynced();
        $headerFile = $header ?? ($letterhead['header_file'] ?? $general_setting->email_header);
        $footerFile = $footer ?? ($letterhead['footer_file'] ?? $general_setting->email_footer);
        $waterFile = $water_mark ?? ($letterhead['watermark_file'] ?? $general_setting->email_water_mark);
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
    <style type="text/css">
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: {{ $isBeyond ? '11px' : '13px' }};
            line-height: 1.35;
            color: #1f2a44;
            background: #fff;
        }
        .toolbar { padding: 10px 12px; }
        .toolbar table { width: auto; }
        .toolbar td { padding: 0 6px 0 0; border: 0; }
        .btn {
            display: inline-block;
            padding: 8px 14px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            color: #fff;
            font-size: 13px;
        }
        .btn-info { background: #6c757d; }
        .btn-primary { background: #6449e7; }
        .btn-danger { background: #c0392b; }
        .sheet { position: relative; margin: 0 auto; padding: {{ $isBeyond ? '0' : '10px' }}; max-width: {{ $isBeyond ? '210mm' : '400px' }}; }
        .letter-header {
            display: block;
            width: 100%;
            max-height: none;
            height: auto;
            object-fit: fill;
            object-position: top;
            margin: 0;
        }
        .letter-footer {
            display: block;
            width: 100%;
            max-height: none;
            height: auto;
            object-fit: fill;
            object-position: bottom;
            margin: 0;
        }
        .content-pad { padding: {{ $isBeyond ? '8px 14px 12px' : '0' }}; }
        .watermark {
            position: absolute;
            top: 38%;
            left: 26%;
            width: 48%;
            opacity: 0.07;
            z-index: 0;
            pointer-events: none;
        }
        .watermark img { width: 100%; }
        .content { position: relative; z-index: 1; }
        .title { text-align: center; font-size: 16px; font-weight: bold; margin: 0 0 4px; }
        .ref-line { text-align: left; font-size: 11px; color: #1f2a44; margin: 0 0 8px; line-height: 1.45; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td {
            width: 50%;
            vertical-align: top;
            padding: 6px 8px;
            border: 1px solid #dfe3ec;
            background: #f8f9fc;
        }
        .label {
            display: block;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1f2a44;
            margin-bottom: 3px;
        }
        .name { font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            background: #d9ebe1;
            color: #1f3d32;
            border-top: 1px solid #b7d4c4;
            border-bottom: 1px solid #b7d4c4;
            padding: 5px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.items td {
            padding: 5px 6px;
            border-bottom: 1px solid #e3efe8;
            vertical-align: top;
        }
        table.items tr.alt td { background: #f2f8f4; }
        table.items tr.total-row td { background: #e5f2eb; font-weight: bold; }
        .num, .qty { text-align: center; }
        .money { text-align: right; white-space: nowrap; }
        table.summary { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.summary > tbody > tr > td { vertical-align: top; }
        .summary-left { width: 56%; padding-right: 10px; }
        .summary-right { width: 44%; }
        .box {
            border: 1px solid #dfe3ec;
            padding: 6px 8px;
            margin-bottom: 6px;
        }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals th, table.totals td {
            padding: 4px 6px;
            border-bottom: 1px solid #eceef4;
        }
        table.totals th { text-align: left; font-weight: normal; }
        table.totals td { text-align: right; }
        table.totals tr.grand th, table.totals tr.grand td {
            font-weight: bold;
            font-size: 12px;
            color: #1f3d32;
            background: #e5f2eb;
        }
        .status {
            display: inline-block;
            padding: 1px 6px;
            border: 1px solid #c9cfdf;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .codes { margin-top: 8px; text-align: left; }
        .codes .created-by { font-size: 10px; line-height: 1.4; margin-bottom: 6px; text-align: left; }
        .codes .code-media { text-align: center; }
        .codes img { display: block; margin: 0 auto; }
        .thanks { text-align: center; color: #6b7386; margin: 6px 0; }
        .centered { text-align: center; }
        @media print {
            .hidden-print { display: none !important; }
            @page { size: A4; margin: 0; }
            body { font-size: 10.5px; margin: 0; }
            .sheet { max-width: none; width: 100%; padding: 0; }
            .letter-header {
                width: 100%;
                max-height: none;
                object-fit: fill;
            }
            .letter-footer {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                max-height: none;
                object-fit: fill;
                margin: 0;
            }
            .content-pad { padding: 8px 12mm 78px; }
            .watermark { opacity: 0.06; }
        }
    </style>
</head>
<body>
@if(preg_match('~[0-9]~', url()->previous()))
    @php $url = '../../pos'; @endphp
@else
    @php $url = url()->previous(); @endphp
@endif

<div class="hidden-print toolbar">
    <table>
        <tr>
            <td><a href="{{$url}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> {{trans('file.Back')}}</a></td>
            <td><button type="button" onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> {{trans('file.Print')}}</button></td>
            <td><a href="{{ route('sale.pos') }}" class="btn btn-danger"><i class="dripicons-cross"></i> Close</a></td>
        </tr>
    </table>
</div>

<div class="sheet">
    @if($isBeyond && $headerFile)
        <img class="letter-header" src="{{ url('public/logo', $headerFile) }}" alt="">
    @endif

    <div class="content content-pad" id="receipt-data">
    @if($isBeyond && $waterFile)
        <div class="watermark"><img src="{{ url('public/logo', $waterFile) }}" alt=""></div>
    @endif
        @if(! $isBeyond)
            <div class="centered">
                @if($general_setting->site_logo)
                    <img src="{{url('public/logo', $general_setting->site_logo)}}" height="42" width="50" style="margin:8px 0;filter:brightness(0);">
                @endif
                <h2 style="margin:0 0 6px;">{{ @$lims_biller_data->company_name }}</h2>
            </div>
        @endif

        <div class="title">Sales Invoice</div>
        <div class="ref-line">
            <strong>{{ trans('file.reference') }}:</strong> {{ $lims_sale_data->reference_no }}<br>
            <strong>{{ trans('file.Date') }}:</strong> {{ $lims_sale_data->created_at->format('d-m-Y') }}
        </div>

        <table class="meta">
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
                    <span class="label">{{ trans('file.To') }}</span>
                    <span class="name">{{ @$lims_customer_data->name }}</span><br>
                    @if(@$lims_customer_data->phone_number){{ $lims_customer_data->phone_number }}<br>@endif
                    @if(@$lims_customer_data->email){{ $lims_customer_data->email }}<br>@endif
                    @if(@$lims_customer_data->address){{ $lims_customer_data->address }}@endif
                    @if(@$lims_customer_data->city){{ @$lims_customer_data->address ? ', ' : '' }}{{ $lims_customer_data->city }}@endif
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
            <tr>
                <th class="num">#</th>
                <th>{{ trans('file.product') }}</th>
                <th class="qty">{{ trans('file.Qty') }}</th>
                <th class="money">{{ trans('file.Unit Price') }}</th>
                <th class="money">{{ trans('file.Tax') }}</th>
                <th class="money">{{ trans('file.Discount') }}</th>
                <th class="money">{{ trans('file.Subtotal') }}</th>
            </tr>
            </thead>
            <tbody>
            <?php
                $total_product_tax = 0;
                $total_product_discount = 0;
                $total_product_subtotal = 0;
                $invoiceQrUrl = \App\Support\SaleInvoiceQr::scanUrl($lims_sale_data);
            ?>
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
                $unit_price = $qty ? ($lineTotal / $qty) : 0;
                $total_product_tax += $lineTax;
                $total_product_discount += $lineDiscount;
                $total_product_subtotal += $lineTotal;
                ?>
                <tr class="{{ $key % 2 === 1 ? 'alt' : '' }}">
                    <td class="num">{{ $key + 1 }}</td>
                    <td>{{ $product_name }}</td>
                    <td class="qty">{{ $product_sale_data->qty + 0 }}</td>
                    <td class="money">{{ number_format($unit_price, 2) }}</td>
                    <td class="money">{{ number_format($lineTax, 2) }}({{ $product_sale_data->tax_rate + 0 }}%)</td>
                    <td class="money">{{ number_format($lineDiscount, 2) }}</td>
                    <td class="money">{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" style="text-align:right;"><strong>{{ trans('file.Total') }}:</strong></td>
                <td class="money">{{ number_format($total_product_tax, 2) }}</td>
                <td class="money">{{ number_format($total_product_discount, 2) }}</td>
                <td class="money">{{ number_format($total_product_subtotal, 2) }}</td>
            </tr>
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td class="summary-left">
                    <div class="box">
                        <span class="label">{{ trans('file.In Words') }}</span>
                        @if($general_setting->currency_position == 'prefix')
                            {{ $currency->code }} {{ str_replace('-', ' ', $numberInWords) }}
                        @else
                            {{ str_replace('-', ' ', $numberInWords) }} {{ $currency->code }}
                        @endif
                    </div>
                    @if(count($lims_payment_data))
                        <div class="box">
                            <span class="label">{{ trans('file.Payment') }}</span>
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
                        <div class="box">
                            <span class="label">{{ trans('file.Sale Note') }}</span>
                            {!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->sale_note) !!}
                        </div>
                    @endif
                    @if($staffNote !== '')
                        <div class="box">
                            <span class="label">{{ trans('file.Staff Note') }}</span>
                            {!! $lims_sale_data->staff_note !!}
                        </div>
                    @endif
                </td>
                <td class="summary-right">
                    <table class="totals">
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
                        <tr class="grand">
                            <th>{{ trans('file.grand total') }}</th>
                            <td>{{ number_format((float) $lims_sale_data->grand_total, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Amount Paid</th>
                            <td>{{ number_format($paidAmount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Amount Pending</th>
                            <td>{{ number_format(max(0, $dueAmount), 2) }}</td>
                        </tr>
                        <tr>
                            <th>{{ trans('file.Payment Status') }}</th>
                            <td>
                                <span class="status">
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

        <div class="thanks">{{ trans('file.Thank you for shopping with us. Please come again') }}</div>
        <div class="codes">
            @if(@$lims_sale_data->user)
                <div class="created-by">
                    <strong>{{ trans('file.Created By') }}:</strong> {{ $lims_sale_data->user->name }}
                    @if(@$lims_sale_data->user->email)<br>{{ $lims_sale_data->user->email }}@endif
                </div>
            @endif
            <div class="code-media" style="margin:0 0 6px;">
                <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($invoiceQrUrl, 'QRCODE').'" height="48" width="48" alt="qrcode">'; ?>
            </div>
            <div class="code-media">
                <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128').'" height="28" width="160" alt="barcode">'; ?>
            </div>
        </div>
    </div>

    @if($isBeyond && $footerFile)
        <img class="letter-footer" id="print-footer" src="{{ url('public/logo', $footerFile) }}" alt="">
    @endif
</div>

<script type="text/javascript">
    try {
        var myItem = localStorage.getItem('pos-expend');
        localStorage.clear();
        if (myItem !== null) localStorage.setItem('pos-expend', myItem);
    } catch (e) {}
    @if(session()->has('message') || request()->boolean('autoprint'))
    setTimeout(function () { window.print(); }, 600);
    @endif
</script>
</body>
</html>

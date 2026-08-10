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
        $orderTax = (float) ($lims_sale_data->order_tax ?? 0);
        $orderDiscount = (float) ($lims_sale_data->order_discount ?? 0);
        $couponDiscount = (float) ($lims_sale_data->coupon_discount ?? 0);
        $shippingCost = (float) ($lims_sale_data->shipping_cost ?? 0);
        $paidAmount = (float) ($lims_sale_data->paid_amount ?? 0);
        $dueAmount = (float) $lims_sale_data->grand_total - $paidAmount;
        $bookingNote = trim(strip_tags((string) ($lims_sale_data->booking_note ?? '')));
        $staffNote = trim(strip_tags((string) ($lims_sale_data->staff_note ?? '')));
        $bookingStatus = 'Draft';
        if ((int) $lims_sale_data->booking_status === 1) {
            $bookingStatus = 'Complete';
        } elseif ((int) $lims_sale_data->booking_status === 2) {
            $bookingStatus = trans('file.Pending');
        } elseif ((int) $lims_sale_data->booking_status === 3) {
            $bookingStatus = 'Return';
        } elseif ((int) $lims_sale_data->booking_status === 4) {
            $bookingStatus = 'Partial Return';
        }
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
        .watermark img { width: 100%; height: auto; }
        .content { position: relative; z-index: 1; }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 4px 0 8px;
            color: #1f2a44;
        }
        .ref-line { margin-bottom: 8px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.meta td {
            width: 50%;
            vertical-align: top;
            padding: 8px 10px;
            border: 1px solid #dfe3ec;
            background: #f7f8fb;
        }
        .label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            color: #6b7386;
            margin-bottom: 2px;
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
        .codes .inv-admin-sign img,
        .codes .inv-user-sign img { display: block; margin: 2px 0 0; height: 42px; width: auto; max-width: 180px; }
        .codes .inv-sign-date { font-size: 7px; line-height: 1.1; color: #444; }
        .codes .inv-created-email { margin-top: 1px; }
        .codes .code-media { text-align: center; }
        .codes .code-media img { display: block; margin: 0 auto; }
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
    @php
        $url = !empty($lims_sale_data->is_frontend) ? '../../online/bookings/index' : '../../bookings/index';
    @endphp
@else
    @php $url = url()->previous(); @endphp
@endif

<div class="hidden-print toolbar">
    <table>
        <tr>
            <td><a href="{{$url}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> {{trans('file.Back')}}</a></td>
            <td><button type="button" onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> {{trans('file.Print')}}</button></td>
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

        <div class="title">Booking Invoice</div>
        <div class="ref-line">
            <strong>{{ trans('file.reference') }}:</strong> {{ $lims_sale_data->reference_no }}<br>
            <strong>{{ trans('file.Date') }}:</strong> {{ optional($lims_sale_data->created_at)->format('d-m-Y') ?: $lims_sale_data->created_at }}
        </div>

        <table class="meta">
            <tr>
                <td>
                    <strong>{{ trans('file.reference') }}:</strong> {{ $lims_sale_data->reference_no }}<br>
                    <strong>{{ trans('file.Date') }}:</strong> {{ optional($lims_sale_data->created_at)->format('d-m-Y') ?: $lims_sale_data->created_at }}<br>
                    @if(@$lims_warehouse_data->name)
                        <strong>{{ trans('file.Warehouse') }}:</strong> {{ $lims_warehouse_data->name }}<br>
                    @endif
                    <strong>Booking Status:</strong> {{ $bookingStatus }}
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
                <th>Period</th>
                <th class="qty">{{ trans('file.Qty') }}</th>
                <th class="money">{{ trans('file.Unit Price') }}</th>
                <th class="money">{{ trans('file.Tax') }}</th>
                <th class="money">{{ trans('file.Subtotal') }}</th>
            </tr>
            </thead>
            <tbody>
            <?php
                $total_product_tax = 0;
                $total_product_subtotal = 0;
            ?>
            @foreach($lims_product_sale_data as $key => $product_sale_data)
                <?php
                $lims_product_data = \App\Product::find($product_sale_data->product_id);
                if ($product_sale_data->variant_id) {
                    $variant_data = \App\Variant::find($product_sale_data->variant_id);
                    $product_name = ($lims_product_data->name ?? 'Item').' ['.@$variant_data->name.']';
                } else {
                    $product_name = $lims_product_data->name ?? 'Item';
                }
                $lineTax = (float) ($product_sale_data->tax ?? 0);
                $lineTotal = (float) ($product_sale_data->total ?? 0);
                $qty = (float) ($product_sale_data->qty ?: 0);
                $unit_price = $qty ? ($lineTotal / $qty) : 0;
                $total_product_tax += $lineTax;
                $total_product_subtotal += $lineTotal;
                $period = trim(($product_sale_data->start ?? '').' — '.($product_sale_data->end ?? ''), ' —');
                ?>
                <tr class="{{ $key % 2 === 1 ? 'alt' : '' }}">
                    <td class="num">{{ $key + 1 }}</td>
                    <td>{{ $product_name }}</td>
                    <td>{{ $period ?: '—' }}</td>
                    <td class="qty">{{ $product_sale_data->qty + 0 }}</td>
                    <td class="money">{{ number_format($unit_price, 2) }}</td>
                    <td class="money">{{ number_format($lineTax, 2) }}</td>
                    <td class="money">{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5" style="text-align:right;"><strong>{{ trans('file.Total') }}:</strong></td>
                <td class="money">{{ number_format($total_product_tax, 2) }}</td>
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
                    @if($bookingNote !== '')
                        <div class="box">
                            <span class="label">Booking Note</span>
                            {!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->booking_note) !!}
                        </div>
                    @endif
                    @if($staffNote !== '')
                        <div class="box">
                            <span class="label">Staff Note</span>
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
                        <tr>
                            <th>Booking Status</th>
                            <td><span class="status">{{ $bookingStatus }}</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="thanks">{{ trans('file.Thank you for shopping with us. Please come again') }}</div>
        <div class="codes">
            @include('pdf.partials._created_by_signature', [
                'createdUser' => $lims_sale_data->user ?? null,
                'stampDate' => $lims_sale_data->created_at ?? null,
                'createdClass' => 'created-by',
            ])
            <div class="code-media" style="margin:0 0 6px;">
                <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($lims_sale_data->reference_no, 'QRCODE').'" height="48" width="48" alt="qrcode">'; ?>
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
    try { localStorage.clear(); } catch (e) {}
    setTimeout(function () { window.print(); }, 600);
</script>
</body>
</html>

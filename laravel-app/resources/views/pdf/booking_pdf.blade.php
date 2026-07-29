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
    $orderTax = (float) ($lims_sale_data->order_tax ?? 0);
    $orderDiscount = (float) ($lims_sale_data->order_discount ?? 0);
    $couponDiscount = (float) ($lims_sale_data->coupon_discount ?? 0);
    $shippingCost = (float) ($lims_sale_data->shipping_cost ?? 0);
    $paidAmount = (float) ($lims_sale_data->paid_amount ?? 0);
    $dueAmount = max(0, (float) $lims_sale_data->grand_total - $paidAmount);
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

<div class="inv-title">Booking Invoice</div>
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
            <strong>Booking Status:</strong> {{ $bookingStatus }}
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

@if($earliestStart || $latestEnd)
    <div class="inv-schedule">
        <strong>Booking Date:</strong>
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
        <th>Booking / Return</th>
        <th class="inv-qty">{{ trans('file.qty') }}</th>
        <th class="inv-money">{{ trans('file.Unit Price') }}</th>
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
        $rowClass = ($key % 2 === 1) ? 'inv-alt' : '';
        ?>
        <tr class="{{ $rowClass }}">
            <td class="inv-num">{{ $key + 1 }}</td>
            <td>
                {{ $product_name }}
                @if($batch_note)<span class="inv-sub">{{ $batch_note }}</span>@endif
                @if($product_sale_data->tax_rate)
                    <span class="inv-sub">{{ trans('file.Tax') }} ({{ $product_sale_data->tax_rate }}%): {{ number_format((float) $product_sale_data->tax, 2) }}</span>
                @endif
            </td>
            <td>
                <strong>Booking:</strong> {{ $product_sale_data->start ? date('d-m-Y H:i', strtotime($product_sale_data->start)) : '—' }}
                <span class="inv-sub"><strong>Return:</strong> {{ $product_sale_data->end ? date('d-m-Y H:i', strtotime($product_sale_data->end)) : '—' }}</span>
            </td>
            <td class="inv-qty">{{ $product_sale_data->qty + 0 }}</td>
            <td class="inv-money">{{ number_format((float) $unit_price, 2) }}</td>
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
            @if($lims_sale_data->booking_note)
                <div class="inv-box inv-note">
                    <span class="inv-label">Booking Note</span>
                    {!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->booking_note) !!}
                </div>
            @endif
            @if($lims_sale_data->staff_note)
                <div class="inv-box inv-note">
                    <span class="inv-label">Staff Note</span>
                    {!! $lims_sale_data->staff_note !!}
                </div>
            @endif
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
                    <th>Booking Status</th>
                    <td><span class="inv-status">{{ $bookingStatus }}</span></td>
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
        <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($lims_sale_data->reference_no, 'QRCODE').'" height="52" width="52" alt="qrcode">'; ?>
    </div>
    <div class="inv-barcode">
        <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128').'" height="24" width="160" alt="barcode">'; ?>
    </div>
</div>

@include('pdf.partials._invoice_close')
</body>
</html>

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

<div class="inv-title">Booking Invoice</div>
<div class="inv-ref">
    {{ $lims_sale_data->reference_no }}
    &nbsp;&middot;&nbsp; {{ $lims_sale_data->created_at->format('D, M d, Y H:i') }}
</div>

<table class="inv-parties">
    <tr>
        <td>
            <span class="inv-label">{{ trans('file.From') }}</span>
            <span class="inv-name">{{ @$lims_biller_data->company_name ?: @$lims_biller_data->name }}</span><br>
            @if(@$lims_warehouse_data->address){{ $lims_warehouse_data->address }}<br>@endif
            @if(@$lims_biller_data->email){{ $lims_biller_data->email }}<br>@endif
            {{ @$lims_warehouse_data->phone ?: @$lims_biller_data->phone_number }}
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
                @if($product_sale_data->tax_rate)
                    <span class="inv-sub">{{ trans('file.Tax') }} ({{ $product_sale_data->tax_rate }}%): {{ number_format((float) $product_sale_data->tax, 2) }}</span>
                @endif
            </td>
            <td>
                {{ date('d M Y, H:i', strtotime($product_sale_data->start)) }}
                <span class="inv-sub">to {{ date('d M Y, H:i', strtotime($product_sale_data->end)) }}</span>
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
            @if(!empty($lims_payment_data->toarray()) && $lims_payment_data[0] && $lims_payment_data[0]->paying_method == 'JE Method' && in_array('JE-method', $all_permission))
                <div class="inv-box">
                    <span class="inv-label">{{ trans('file.Account') }}</span>
                    {{ trans('file.Credit Account') }}: {{ @$lims_account_data_cradit->name }} / {{ @$lims_account_data_cradit->account_no }} - {{ @$lims_account_data_cradit->departments->code }}<br>
                    {{ trans('file.Debit Account') }}: {{ @$lims_account_data_debit->name }} / {{ @$lims_account_data_debit->account_no }} - {{ @$lims_account_data_debit->departments->code }}
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
            <table class="inv-foot-row">
                <tr>
                    <td class="inv-thanks">{{ trans('file.Thank you for shopping with us. Please come again') }}</td>
                    <td class="inv-codes">
                        <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($lims_sale_data->reference_no, 'C128').'" height="26" width="150" alt="">'; ?>
                        &nbsp;
                        <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($lims_sale_data->reference_no, 'QRCODE').'" height="46" width="46" alt="">'; ?>
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
                    <th>Booking Status</th>
                    <td>
                        <span class="inv-status">
                            @if($lims_sale_data->booking_status == 1)
                                Complete
                            @elseif($lims_sale_data->booking_status == 2)
                                {{ trans('file.Pending') }}
                            @elseif($lims_sale_data->booking_status == 3)
                                Return
                            @elseif($lims_sale_data->booking_status == 4)
                                Partial Return
                            @else
                                Draft
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

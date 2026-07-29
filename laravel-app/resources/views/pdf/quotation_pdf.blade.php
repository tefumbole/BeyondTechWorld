<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $general_setting->site_title }}</title>
    @php $invoiceLetterhead = $letterhead ?? \App\Support\Letterhead::ensureSynced(); @endphp
    @include('pdf.partials._invoice_styles')
</head>
<body>
@include('pdf.partials._invoice_open')

<div class="inv-title">{{ trans('file.Quotation') }}</div>
<div class="inv-ref">
    {{ $lims_sale_data->reference_no }}
    &nbsp;&middot;&nbsp; {{ $lims_sale_data->created_at->format('D, M d, Y H:i') }}
</div>

<table class="inv-parties">
    <tr>
        <td>
            <span class="inv-label">{{ trans('file.From') }}</span>
            <span class="inv-name">{{ @$lims_sale_data->biller->company_name ?: @$lims_sale_data->biller->name }}</span><br>
            @if(@$lims_sale_data->biller->address){{ $lims_sale_data->biller->address }}<br>@endif
            @if(@$lims_sale_data->biller->email){{ $lims_sale_data->biller->email }}<br>@endif
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
        <col style="width:4%"><col style="width:36%"><col style="width:11%"><col style="width:7%">
        <col style="width:13%"><col style="width:10%"><col style="width:19%">
    </colgroup>
    <thead>
    <tr>
        <th class="inv-num">#</th>
        <th>{{ trans('file.product') }}</th>
        <th>{{ trans('file.Batch No') }}</th>
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
        $product_batch_name = 'N/A';
        $multi_product_batch_id = null;
        $multi_product_batch_qty = null;
        if ($product_sale_data->multi_product_batch_id != null) {
            $multi_product_batch_id = json_decode($product_sale_data->multi_product_batch_id);
            $multi_product_batch_qty = json_decode($product_sale_data->multi_product_batch_qty);
        }
        $lims_product_data = \App\Product::find($product_sale_data->product_id);
        if ($product_sale_data->variant_id) {
            $variant_data = \App\Variant::find($product_sale_data->variant_id);
            $product_name = $lims_product_data->name.' ['.@$variant_data->name.']';
        } elseif ($product_sale_data->product_batch_id) {
            $product_name = $lims_product_data->name;
            if (! $multi_product_batch_id) {
                $product_batch_data = \App\ProductBatch::select('batch_no')->find($product_sale_data->product_batch_id);
                $product_batch_name = @$product_batch_data->batch_no ?: 'N/A';
            } else {
                $batches = [];
                foreach ($multi_product_batch_id as $batch_id) {
                    $product_batch_data = \App\ProductBatch::select('batch_no')->find($batch_id);
                    if (@$product_batch_data->batch_no) {
                        $batches[] = $product_batch_data->batch_no;
                    }
                }
                $product_batch_name = $batches ? implode(', ', $batches) : 'N/A';
            }
        } else {
            $product_name = $lims_product_data->name;
        }
        if ($product_sale_data->tax) {
            $total_product_tax += $product_sale_data->tax;
        }
        ?>
        <tr>
            <td class="inv-num">{{ $key + 1 }}</td>
            <td>{{ $product_name }}</td>
            <td>{{ $product_batch_name }}</td>
            <td class="inv-qty">{{ $product_sale_data->qty + 0 }}</td>
            <td class="inv-money">{{ number_format((float) $product_sale_data->net_unit_price, 2) }}</td>
            <td class="inv-money">
                @if($product_sale_data->tax > 0)
                    {{ number_format((float) $product_sale_data->tax, 2) }}
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
            @if(trim(strip_tags((string) $lims_sale_data->note)) !== '')
                <div class="inv-box inv-note">
                    <span class="inv-label">{{ trans('file.Note') }}</span>
                    {!! \App\Support\BookingNoteFormatter::forDisplay($lims_sale_data->note) !!}
                </div>
            @endif
            <div class="inv-box">
                <span class="inv-label">{{ trans('file.Created By') }}</span>
                {{ @$lims_sale_data->user->name }}
                @if(@$lims_sale_data->user->phone) &middot; {{ $lims_sale_data->user->phone }} @endif
            </div>
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
            </table>
        </td>
    </tr>
</table>

@include('pdf.partials._invoice_close')
</body>
</html>

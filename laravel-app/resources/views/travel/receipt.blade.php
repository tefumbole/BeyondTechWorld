<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EPIC TRAVELS — {{ $parcel->tracking_ref }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.35; color: #1f2a44; background: #eef2ea; }
        .toolbar { max-width: 210mm; margin: 16px auto; display: flex; gap: 8px; }
        .toolbar a, .toolbar button { border: 0; border-radius: 10px; padding: 10px 16px; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar .print { background: #124638; color: #fff; }
        .toolbar .back { background: #fff; color: #132d29; }
        .sheet { position: relative; max-width: 210mm; margin: 0 auto 24px; background: #fff; min-height: 270mm; }
        .letterhead { background: #124638; color: #fff; padding: 22px 28px 18px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .mark { width: 46px; height: 46px; border-radius: 12px; background: #d9f68e; color: #124638; display: grid; place-items: center; font-weight: 800; }
        .brand strong { display: block; font-size: 22px; letter-spacing: .04em; }
        .brand span { color: #d7eadf; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .letterhead em { font-style: normal; font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: #d9f68e; }
        .content { padding: 18px 22px 28px; }
        .title { text-align: center; font-size: 16px; font-weight: 800; margin: 0 0 4px; }
        .ref-line { margin: 0 0 10px; }
        table.meta, table.items, table.summary, table.totals { width: 100%; border-collapse: collapse; }
        table.meta td { width: 50%; vertical-align: top; padding: 8px 10px; border: 1px solid #dfe3ec; background: #f8f9fc; }
        .label { display: block; font-size: 9px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 3px; }
        .name { font-weight: 800; }
        table.items { margin-top: 10px; }
        table.items th { background: #d9ebe1; color: #1f3d32; border-top: 1px solid #b7d4c4; border-bottom: 1px solid #b7d4c4; padding: 6px; text-align: left; font-size: 10px; text-transform: uppercase; }
        table.items td { padding: 6px; border-bottom: 1px solid #e3efe8; }
        table.items tr.total-row td { background: #e5f2eb; font-weight: 800; }
        .money, .qty { text-align: right; white-space: nowrap; }
        .qty { text-align: center; }
        table.summary { margin-top: 10px; }
        table.summary > tbody > tr > td { vertical-align: top; }
        .summary-left { width: 56%; padding-right: 10px; }
        .summary-right { width: 44%; }
        .box { border: 1px solid #dfe3ec; padding: 8px 10px; margin-bottom: 8px; }
        table.totals th, table.totals td { padding: 5px 6px; border-bottom: 1px solid #eceef4; }
        table.totals th { text-align: left; font-weight: normal; }
        table.totals td { text-align: right; }
        table.totals tr.grand th, table.totals tr.grand td { font-weight: 800; font-size: 13px; color: #1f3d32; background: #e5f2eb; }
        .status { display: inline-block; padding: 1px 6px; border: 1px solid #c9cfdf; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .thanks { text-align: center; color: #6b7386; margin: 14px 0 0; }
        .letterfoot { background: #124638; color: #d9f68e; text-align: center; letter-spacing: .12em; font-size: 11px; font-weight: 800; padding: 12px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; max-width: none; min-height: auto; }
            @page { size: A4; margin: 0; }
            .letterfoot { position: fixed; left: 0; right: 0; bottom: 0; }
            .content { padding-bottom: 48px; }
        }
    </style>
</head>
<body>
@php
    $paid = $parcel->payment_status === 'paid';
    $channel = $payment && $payment->channel === 'cash' ? 'Cash' : 'Mobile Money';
    $packages = max(1, (int) $parcel->package_count);
    $fee = (int) $parcel->fee_xaf;
@endphp
<div class="toolbar">
    <a class="back" href="{{ route('travel.parcels') }}">All parcels</a>
    <button class="print" type="button" onclick="window.print()">Print receipt</button>
</div>
<div class="sheet">
    <header class="letterhead">
        <div class="brand">
            <span class="mark">ET</span>
            <div>
                <strong>EPIC TRAVELS</strong>
                <span>Intercity buses &amp; parcels · Cameroon</span>
            </div>
        </div>
        <em>Parcel receipt</em>
    </header>
    <div class="content">
        @if(session('status'))<p class="box">{{ session('status') }}</p>@endif
        <div class="title">Parcel receipt</div>
        <div class="ref-line">
            <strong>Reference:</strong> {{ $parcel->tracking_ref }}<br>
            <strong>Date:</strong> {{ \Carbon\Carbon::parse($parcel->created_at)->format('d-m-Y') }}
        </div>
        <table class="meta">
            <tr>
                <td>
                    <span class="label">From</span>
                    <span class="name">{{ $parcel->sender_name }}</span><br>
                    {{ $parcel->sender_phone }}<br>
                    {{ $origin }}
                </td>
                <td>
                    <span class="label">To</span>
                    <span class="name">{{ $parcel->receiver_name }}</span><br>
                    {{ $parcel->receiver_phone }}<br>
                    {{ $destination }}
                </td>
            </tr>
        </table>
        <table class="items">
            <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="qty">Qty</th>
                <th class="money">Fee</th>
                <th class="money">Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>1</td>
                <td>{{ $parcel->description }}<br>{{ $origin }} to {{ $destination }}@if($parcel->expected_on) · expected {{ \Carbon\Carbon::parse($parcel->expected_on)->format('d-m-Y') }}@endif</td>
                <td class="qty">{{ $packages }}</td>
                <td class="money">{{ number_format($fee, 2) }}</td>
                <td class="money">{{ number_format($fee, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="4" style="text-align:right;">Total</td>
                <td class="money">{{ number_format($fee, 2) }}</td>
            </tr>
            </tbody>
        </table>
        <table class="summary">
            <tr>
                <td class="summary-left">
                    @if($words !== '')
                        <div class="box">
                            <span class="label">In words</span>
                            {{ ucfirst($words) }} CFA francs
                        </div>
                    @endif
                    <div class="box">
                        <span class="label">Payment</span>
                        {{ $channel }}: {{ number_format($paid ? $fee : 0, 2) }}
                    </div>
                </td>
                <td class="summary-right">
                    <table class="totals">
                        <tr class="grand">
                            <th>Grand total</th>
                            <td>{{ number_format($fee, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Amount paid</th>
                            <td>{{ number_format($paid ? $fee : 0, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Amount pending</th>
                            <td>{{ number_format($paid ? 0 : $fee, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Payment status</th>
                            <td><span class="status">{{ $paid ? 'Paid' : 'Pending' }}</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <p class="thanks">Thank you for choosing Epic Travels.</p>
    </div>
    <footer class="letterfoot">EPIC TRAVELS · CAMEROON</footer>
</div>
</body>
</html>

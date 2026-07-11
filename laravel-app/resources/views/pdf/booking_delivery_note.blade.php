<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Goods Delivery Note - {{ $receipt->reference_no }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #0b3f90; color: #fff; }
        h2, h3 { margin: 0 0 8px; }
        .meta { margin-bottom: 16px; }
    </style>
</head>
<body>
    <h2>Goods Delivery Note</h2>
    <div class="meta">
        <strong>Delivery Ref:</strong> {{ $receipt->reference_no }}<br>
        <strong>Booking Ref:</strong> {{ $booking->reference_no }}<br>
        <strong>Date:</strong> {{ $receipt->created_at->format('d M Y, H:i') }}<br>
        <strong>Customer:</strong> {{ optional($booking->customer)->name ?? '-' }}<br>
        <strong>Facility:</strong> {{ optional($booking->biller)->name ?? '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Equipment</th>
                <th>Code</th>
                <th>Qty</th>
                <th>From</th>
                <th>To</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['code'] }}</td>
                    <td>{{ $item['qty'] }}</td>
                    <td>{{ $item['start'] ? date('d/m/Y H:i', strtotime($item['start'])) : '-' }}</td>
                    <td>{{ $item['end'] ? date('d/m/Y H:i', strtotime($item['end'])) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:20px;"><em>This document lists equipment delivered. No pricing is shown.</em></p>
</body>
</html>

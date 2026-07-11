<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Signed Goods Received - {{ $receipt->reference_no }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #0b3f90; color: #fff; }
        .signatures { margin-top: 24px; width: 100%; }
        .signatures td { border: 0; vertical-align: top; width: 50%; padding: 0 12px 0 0; }
        .signature-box { border: 1px solid #ddd; padding: 10px; min-height: 120px; }
        .signature-box img { max-width: 220px; max-height: 90px; }
    </style>
</head>
<body>
    <h2>Goods Delivery &amp; Receipt Confirmation</h2>
    <p>
        <strong>Delivery Ref:</strong> {{ $receipt->reference_no }}<br>
        <strong>Booking Ref:</strong> {{ $booking->reference_no }}<br>
        <strong>Customer:</strong> {{ optional($booking->customer)->name ?? '-' }}
    </p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Equipment</th>
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
                    <td>{{ $item['qty'] }}</td>
                    <td>{{ $item['start'] ? date('d/m/Y H:i', strtotime($item['start'])) : '-' }}</td>
                    <td>{{ $item['end'] ? date('d/m/Y H:i', strtotime($item['end'])) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <strong>Delivered By</strong>
                @if(!empty($receipt->delivered_by_name))
                    ({{ $receipt->delivered_by_name }})
                @endif
                <br>
                <span style="font-size:11px;color:#666;">
                    {{ $receipt->delivered_signed_at ? $receipt->delivered_signed_at->format('d M Y, H:i') : 'Not signed' }}
                </span>
                <div class="signature-box">
                    @if(!empty($deliveredSignatureSrc))
                        <img src="{{ $deliveredSignatureSrc }}" alt="Delivered signature">
                    @endif
                </div>
            </td>
            <td>
                <strong>Received By</strong> ({{ optional($booking->customer)->name ?? 'Client' }})<br>
                <span style="font-size:11px;color:#666;">
                    {{ $receipt->signed_at ? $receipt->signed_at->format('d M Y, H:i') : 'Not signed' }}
                </span>
                <div class="signature-box">
                    @if(!empty($signatureSrc))
                        <img src="{{ $signatureSrc }}" alt="Received signature">
                    @endif
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

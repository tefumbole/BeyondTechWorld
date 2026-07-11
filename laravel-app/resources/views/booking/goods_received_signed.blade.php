<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Goods Received Signed</title>
    <style>
        body { font-family: Nunito, sans-serif; background: #f4f7fb; margin: 0; padding: 40px 16px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 28px; text-align: center; box-shadow: 0 8px 24px rgba(11,63,144,.08); }
        h1 { color: #0b3f90; }
    </style>
</head>
<body>
    <div class="card">
        @if(session()->has('message'))
            <p style="color:#28a745;">{{ session()->get('message') }}</p>
        @endif
        @php $isDelivered = ($role ?? 'received') === 'delivered'; @endphp
        <h1>{{ $isDelivered ? 'Goods Delivery Signed' : 'Goods Received Signed' }}</h1>
        <p>Delivery Note: <strong>{{ $receipt->reference_no }}</strong></p>
        @if($isDelivered)
            <p>Signed on: <strong>{{ $receipt->delivered_signed_at ? $receipt->delivered_signed_at->format('d M Y, H:i') : '-' }}</strong></p>
            <p>Thank you for confirming delivery of the equipment.</p>
        @else
            <p>Signed on: <strong>{{ $receipt->signed_at ? $receipt->signed_at->format('d M Y, H:i') : '-' }}</strong></p>
            <p>Thank you for confirming receipt of the equipment.</p>
        @endif
    </div>
</body>
</html>

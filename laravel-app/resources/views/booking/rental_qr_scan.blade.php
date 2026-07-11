<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rental Details - {{ $booking->reference_no }}</title>
    <link rel="icon" type="image/png" href="{{ url('public/logo', $general_setting->site_logo) }}" />
    <style>
        :root {
            --primary: #0b3f90;
            --accent: #c6ab47;
            --text: #1f2a44;
            --muted: #6f7b91;
        }
        body {
            margin: 0;
            font-family: "Nunito", sans-serif;
            background: #f3f6fb;
            color: var(--text);
        }
        .wrap { max-width: 920px; margin: 0 auto; padding: 24px 16px 40px; }
        .hero {
            background: linear-gradient(135deg, #0b3f90, #072f6b);
            color: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 18px;
        }
        .hero h1 { margin: 0 0 8px; font-size: 28px; }
        .hero p { margin: 0; color: #dce7ff; }
        .card {
            background: #fff;
            border: 1px solid #e3e9f4;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(15, 35, 80, 0.05);
        }
        .card h3 { margin: 0 0 12px; color: var(--primary); font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #eef2f8; text-align: left; font-size: 14px; }
        th { color: var(--primary); background: #f8faff; }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(0, 168, 107, 0.12);
            color: #008f5d;
            font-size: 12px;
            font-weight: 700;
        }
        .comments {
            background: #fffdf5;
            border: 1px solid #f0e3a8;
            border-radius: 10px;
            padding: 14px 16px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>{{ $general_setting->site_title ?? 'Equipment Rental' }}</h1>
        <p>Booking Ref: <strong>{{ $booking->reference_no }}</strong> · Client: <strong>{{ optional($booking->customer)->name }}</strong></p>
        <p>Signed on {{ $contract->signed_at ? $contract->signed_at->format('d M Y, H:i') : 'N/A' }} · <span class="badge">Active Rental</span></p>
    </div>

    <div class="card">
        <h3>Rented Equipment</h3>
        <table>
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Code</th>
                    <th>Qty</th>
                    <th>Return By</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['code'] }}</td>
                        <td>{{ $item['qty'] }}</td>
                        <td>{{ $item['end'] ? date('d M Y, H:i', strtotime($item['end'])) : 'As scheduled' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(!empty($booking->booking_note) || !empty($booking->staff_note))
        <div class="card">
            <h3>Comments & Notes</h3>
            @if(!empty($booking->booking_note))
                <div class="comments mb-3">
                    <strong>Client / Booking Comments</strong><br>
                    {{ $booking->booking_note }}
                </div>
            @endif
            @if(!empty($booking->staff_note))
                <div class="comments">
                    <strong>Staff Notes</strong><br>
                    {{ $booking->staff_note }}
                </div>
            @endif
        </div>
    @endif

    <div class="card">
        <h3>Facility</h3>
        <p><strong>{{ optional($booking->biller)->name }}</strong></p>
        <p>{{ optional($booking->biller)->address }}</p>
        <p>{{ optional($booking->biller)->phone_number }}</p>
    </div>
</div>
</body>
</html>

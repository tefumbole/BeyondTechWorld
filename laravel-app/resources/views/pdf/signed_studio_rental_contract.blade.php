<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Signed Studio Rental Agreement - {{ $booking->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2a44; margin: 24px; position: relative; }
        .watermark {
            position: fixed;
            top: 28%;
            left: 12%;
            width: 76%;
            opacity: 0.08;
            z-index: 0;
        }
        .content { position: relative; z-index: 1; }
        .contract-section { margin-bottom: 14px; page-break-inside: avoid; }
        .contract-section h5 { color: #0b3f90; margin: 0 0 6px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d7deea; padding: 8px; text-align: left; }
        th { background: #eef3fb; color: #0b3f90; }
        .signature img { max-width: 240px; max-height: 100px; border: 1px solid #c6ab47; }
        .meta { margin-bottom: 12px; }
        .meta h4 { color: #0b3f90; margin: 0 0 8px; }
        .booking-note p, .booking-note li { margin: 0 0 6px; }
    </style>
</head>
<body>
    @if(!empty($watermarkPath) && file_exists($watermarkPath))
        <img src="{{ $watermarkPath }}" class="watermark" alt="">
    @endif

    <div class="content">
    @if(!empty($headerPath) && file_exists($headerPath) && ($general_setting->invoice_format ?? '') === 'beyond_a4')
        <img src="{{ $headerPath }}" style="width:100%;margin-bottom:12px;">
    @else
        <div style="text-align:center;margin-bottom:12px;border-bottom:2px solid #0b3f90;padding-bottom:8px;">
            @if(!empty($general_setting->site_logo))
                <img src="{{ public_path('logo/' . $general_setting->site_logo) }}" height="48">
            @endif
            <h2 style="color:#0b3f90;margin:4px 0;">{{ optional($booking->biller)->company_name ?? ($general_setting->site_title ?? 'Studio Rental') }}</h2>
        </div>
    @endif

    <div class="meta">
        <h4>Studio Rental Agreement</h4>
        <p><strong>Booking Ref:</strong> {{ $booking->reference_no }}</p>
        <p><strong>Client:</strong> {{ optional($booking->customer)->name }}</p>
        @if($contract->signed_at)
            <p><strong>Client Signed:</strong> {{ $contract->signed_at->format('d M Y, H:i') }}</p>
        @endif
        @if($contract->admin_signed_at)
            <p><strong>Admin Signed:</strong> {{ $contract->admin_signed_at->format('d M Y, H:i') }} ({{ optional($contract->adminSigner)->name }})</p>
        @endif
    </div>

    <div class="contract-section">
        <h5>1. Studio Session Summary</h5>
        <p>Studio rentals may be booked Hourly, Daily, or Monthly. Sessions below show method, duration, and schedule.</p>
        <table>
            <thead>
                <tr>
                    <th>Studio / Service</th>
                    <th>Code</th>
                    <th>Method</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['code'] }}</td>
                        <td>{{ $item['booking_method'] ?? 'Hourly' }}@if(!empty($item['number_duration'])) × {{ $item['number_duration'] }}@endif</td>
                        <td>{{ $item['qty'] }}</td>
                        <td>{{ number_format($item['unit_price'], 2) }}</td>
                        <td>{{ number_format($item['total'], 2) }}</td>
                        <td>{{ $item['start'] ? date('d M Y, H:i', strtotime($item['start'])) : 'As agreed' }}</td>
                        <td>{{ $item['end'] ? date('d M Y, H:i', strtotime($item['end'])) : 'As agreed' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p><strong>Grand Total: {{ number_format($booking->grand_total, 2) }}</strong></p>
        <p><strong>Paid Amount: {{ number_format($booking->paid_amount, 2) }}</strong></p>
        <p><strong>Balance Due: {{ number_format($booking->grand_total - $booking->paid_amount, 2) }}</strong></p>
    </div>

    <div class="contract-section">
        <h5>2. Settling-In Time &amp; Extensions</h5>
        <p>Allow enough time for settling in and wrap-up. When booked time ends, adding another hour is subject to approval and availability.</p>
    </div>
    <div class="contract-section">
        <h5>3. Overtime Charges</h5>
        <p>Overtime is billed at 12,000 XAF for 0–60 minutes (or any part thereof), unless a longer extension is separately approved and priced.</p>
    </div>
    <div class="contract-section">
        <h5>4. Non-Refundable &amp; Generator Fuel</h5>
        <p>Studio rentals are not refundable. Generator fuel is 3,200 XAF per hour and must be requested by the client. If fuel is not requested and there is an outage, the session is lost with no refund.</p>
    </div>
    <div class="contract-section">
        <h5>5. Care of Studio &amp; Equipment</h5>
        <p>The client must use the studio carefully. Damage, loss, or misuse may be charged at repair or replacement cost.</p>
    </div>

    @if(!empty($booking->booking_note))
        <div class="contract-section booking-note">
            <h5>Additional Notes</h5>
            <div>{!! \App\Support\BookingNoteFormatter::forDisplay($booking->booking_note) !!}</div>
        </div>
    @endif

    <div class="contract-section">
        <h5>Acceptance &amp; Signatures</h5>
        <table style="border:none;">
            <tr>
                <td style="border:none;width:50%;vertical-align:top;">
                    <p><strong>Client Signature</strong></p>
                    <div class="signature">
                        @if(!empty($signatureFilePath))
                            <img src="{{ $signatureFilePath }}" alt="Client signature">
                        @endif
                    </div>
                </td>
                <td style="border:none;width:50%;vertical-align:top;">
                    <p><strong>Authorized Signatory</strong></p>
                    <div class="signature">
                        @if(!empty($adminSignatureFilePath))
                            <img src="{{ $adminSignatureFilePath }}" alt="Admin signature">
                        @else
                            <p><em>Pending admin countersign</em></p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($footerPath) && file_exists($footerPath) && ($general_setting->invoice_format ?? '') === 'beyond_a4')
        <img src="{{ $footerPath }}" style="width:100%;margin-top:16px;">
    @endif
    </div>
</body>
</html>

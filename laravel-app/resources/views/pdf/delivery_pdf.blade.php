<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery {{ $delivery->reference_no }}</title>
    @include('pdf.partials._invoice_styles')
    <style type="text/css">
        .inv-title { margin-bottom: 4px; }
        .inv-ref { margin-bottom: 6px; }
        table.inv-meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.inv-meta td {
            width: 50%; vertical-align: top; padding: 6px 8px;
            border: 1px solid #dfe3ec; background: #f8f9fc; font-size: 10px; line-height: 1.35;
        }
        table.inv-meta .inv-label {
            display: block; font-size: 9px; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.5px; color: #1f2a44; margin-bottom: 3px;
        }
        .inv-codes-block { margin-top: 8px; page-break-inside: avoid; text-align: left; }
        .inv-codes-block .inv-created { font-size: 10px; line-height: 1.4; margin-bottom: 6px; text-align: left; }
        .inv-codes-block .inv-qr, .inv-codes-block .inv-barcode { text-align: center; }
        .inv-codes-block img { display: block; margin: 0 auto; }
        .sig-box { border: 1px solid #dfe3ec; padding: 8px; margin-top: 8px; }
        .sig-box img { max-height: 70px; }
    </style>
</head>
<body>
@include('pdf.partials._invoice_open')

@php
    $sale = $delivery->sale;
    $customer = $sale ? $sale->customer : null;
    $verifyUrl = isset($verifyUrl) ? $verifyUrl : \App\Support\DeliveryVerifyQr::scanUrl($delivery);
    $sigFsPath = $delivery->client_signature_path
        ? public_path(ltrim(str_replace('\\', '/', $delivery->client_signature_path), '/'))
        : null;
@endphp

<div class="inv-title">{{ $delivery->isSigned() ? 'Signed Delivery' : 'Delivery Note' }}</div>
<div class="inv-ref">
    <strong>{{ trans('file.reference') }}:</strong> {{ $delivery->reference_no }}<br>
    <strong>{{ trans('file.Date') }}:</strong> {{ optional($delivery->created_at)->format('d-m-Y') }}
</div>

<table class="inv-meta">
    <tr>
        <td>
            <strong>Date:</strong> {{ optional($delivery->created_at)->format('d-m-Y') }}<br>
            <strong>Delivery Reference:</strong> {{ $delivery->reference_no }}<br>
            <strong>Sale Reference:</strong> {{ optional($sale)->reference_no }}<br>
            <strong>Status:</strong> {{ $delivery->statusLabel() }}
            @if($delivery->isSigned())
                <br><strong>Signature:</strong> Signed
            @else
                <br><strong>Signature:</strong> Pending
            @endif
        </td>
        <td>
            <span class="inv-label">Customer</span>
            <strong>{{ optional($customer)->name }}</strong><br>
            @if(optional($customer)->phone_number){{ $customer->phone_number }}<br>@endif
            {{ $delivery->address }}
        </td>
    </tr>
</table>

<table class="inv-items">
    <thead>
    <tr>
        <th class="inv-num">#</th>
        <th>Code</th>
        <th>Description</th>
        <th class="inv-qty">Qty</th>
    </tr>
    </thead>
    <tbody>
    @foreach($lines as $i => $line)
        <tr class="{{ $i % 2 === 1 ? 'inv-alt' : '' }}">
            <td class="inv-num">{{ $i + 1 }}</td>
            <td>{{ $line['code'] }}</td>
            <td>{{ $line['name'] }}</td>
            <td class="inv-qty">{{ $line['qty'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="inv-meta" style="margin-top:8px;">
    <tr>
        <td>
            <span class="inv-label">Delivered By</span>
            {{ $delivery->delivered_by ?: '—' }}
        </td>
        <td>
            <span class="inv-label">Received By</span>
            {{ $delivery->recieved_by ?: '—' }}
            @if($delivery->signer_name && $delivery->signer_name !== $delivery->recieved_by)
                <br><small>Signed as: {{ $delivery->signer_name }}</small>
            @endif
        </td>
    </tr>
</table>

@if($delivery->note)
    <div class="inv-box" style="margin-top:6px;">
        <span class="inv-label" style="color:#1f2a44;">Note</span>
        {{ $delivery->note }}
    </div>
@endif

@if($sigFsPath && file_exists($sigFsPath))
    <div class="sig-box">
        <strong>Client signature</strong><br>
        <img src="{{ $sigFsPath }}" alt="Signature">
        @if($delivery->client_signed_at)
            <div style="font-size:9px;color:#5b6478;margin-top:4px;">
                Signed {{ $delivery->client_signed_at->format('d-m-Y H:i') }}
            </div>
        @endif
    </div>
@endif

<div class="inv-codes-block">
    <div class="inv-created">
        <strong>Created By:</strong> {{ optional($delivery->user)->name }}
        @if(optional($delivery->user)->email)<br>{{ $delivery->user->email }}@endif
    </div>
    <div class="inv-qr" style="margin:0 0 6px;">
        <?php echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($verifyUrl, 'QRCODE').'" height="52" width="52" alt="qrcode">'; ?>
    </div>
    <div class="inv-barcode">
        <?php echo '<img src="data:image/png;base64,'.DNS1D::getBarcodePNG($delivery->reference_no, 'C128').'" height="24" width="160" alt="barcode">'; ?>
    </div>
</div>

@include('pdf.partials._invoice_close')
</body>
</html>

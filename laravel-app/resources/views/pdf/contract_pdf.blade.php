<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    @php
        $use_system_letterhead = true;
        $letterhead = $letterhead ?? \App\Support\Letterhead::ensureSynced();
        $hasLetterhead = ! empty($letterhead['has_header']);
        $general_setting = $general_setting ?? \App\GeneralSetting::query()->orderByDesc('id')->first();
    @endphp
    @if($general_setting)
        <link rel="icon" type="image/png" href="{{ url('public/logo', $general_setting->site_logo) }}" />
        <title>{{ $contract->number ?? 'Contract' }} — {{ $general_setting->site_title }}</title>
    @else
        <title>{{ $contract->number ?? 'Contract' }}</title>
    @endif
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('pdf.partials._letter_branded_styles')
    <style type="text/css">
        * {
            font-size: 13px;
            line-height: 22px;
            font-family: DejaVu Sans, 'Ubuntu', sans-serif;
        }
        body { margin: 0; padding: 0; }
        .contract-meta {
            margin: 0 0 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .contract-meta h2 {
            font-size: 18px;
            margin: 0 0 6px;
            color: #0b3f90;
        }
        .contract-body { margin-top: 12px; }
        .contract-body p { margin: 0 0 10px; }
        .contract-body h1, .contract-body h2, .contract-body h3 { margin: 12px 0 8px; }
        .contract-body table { width: 100%; border-collapse: collapse; }
        .contract-body td, .contract-body th { padding: 6px 8px; border: 1px solid #e5e7eb; }
        @if(! empty($draftWatermark))
        .draft-watermark {
            position: fixed;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 72px;
            font-weight: bold;
            color: rgba(200, 0, 0, 0.12);
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
        }
        @endif
    </style>
</head>
<body>
@if(! empty($draftWatermark))
    <div class="draft-watermark">DRAFT</div>
@endif

@include('pdf.partials._letter_branded_open')

<div class="contract-meta">
    <h2>{{ $contract->title ?? 'Contract' }}</h2>
    <div><strong>Reference:</strong> {{ $contract->number ?? '' }}</div>
    @if(! empty($contract->effective_date))
        <div><strong>Effective date:</strong> {{ \Carbon\Carbon::parse($contract->effective_date)->format('d F Y') }}</div>
    @endif
    @if(! empty($contract->jurisdiction))
        <div><strong>Jurisdiction:</strong> {{ $contract->jurisdiction }}</div>
    @endif
</div>

<div class="contract-body">
    {!! $bodyHtml !!}
</div>

@include('pdf.partials._letter_branded_close')
</body>
</html>

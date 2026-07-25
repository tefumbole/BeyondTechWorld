<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $use_system_letterhead = true;
        $letterhead = $letterhead ?? \App\Support\Letterhead::ensureSynced();
        $general_setting = $general_setting ?? \App\GeneralSetting::query()->orderByDesc('id')->first();
        $revision = $revision ?? optional($contract)->currentRevision;
        $signatories = $contract->signatories ?? collect();
        $signedAt = $contract->signed_at ?? null;
    @endphp
    @if($general_setting)
        <title>Certificate — {{ $contract->number ?? '' }}</title>
    @else
        <title>Contract Completion Certificate</title>
    @endif
    @include('pdf.partials._letter_branded_styles')
    <style type="text/css">
        * {
            font-size: 13px;
            line-height: 22px;
            font-family: DejaVu Sans, 'Ubuntu', sans-serif;
        }
        .cert-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #0b3f90;
            margin: 20px 0 24px;
        }
        .cert-box {
            border: 2px solid #0b3f90;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px 10px; border: 1px solid #d7deea; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; }
        .checksum {
            font-family: monospace;
            font-size: 11px;
            word-break: break-all;
            color: #475569;
        }
    </style>
</head>
<body>
@include('pdf.partials._letter_branded_open')

<div class="cert-title">Contract Completion Certificate</div>

<div class="cert-box">
    <p>This certifies that the following contract was fully executed and all required signatures were collected.</p>
    <p><strong>Contract number:</strong> {{ $contract->number ?? '—' }}</p>
    <p><strong>Title:</strong> {{ $contract->title ?? '—' }}</p>
    <p><strong>Signed at:</strong> {{ $signedAt ? \Carbon\Carbon::parse($signedAt)->format('d F Y H:i') : '—' }}</p>
    <p><strong>Revision:</strong> {{ optional($revision)->revision_no ?? '—' }}</p>
    <p><strong>Document checksum (SHA-256):</strong></p>
    <p class="checksum">{{ $checksum ?? optional($revision)->checksum ?? '—' }}</p>
</div>

<h3 style="color:#0b3f90;margin-top:24px;">Signatories</h3>
<table>
    <thead>
        <tr>
            <th>Role</th>
            <th>Name</th>
            <th>Status</th>
            <th>Signed at</th>
        </tr>
    </thead>
    <tbody>
        @forelse($signatories as $sig)
            <tr>
                <td>{{ ucwords(str_replace('_', ' ', $sig->role)) }}</td>
                <td>{{ $sig->typed_name ?: $sig->display_name }}</td>
                <td>{{ ucfirst($sig->status) }}</td>
                <td>{{ $sig->signed_at ? \Carbon\Carbon::parse($sig->signed_at)->format('d M Y H:i') : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No signatory records.</td></tr>
        @endforelse
    </tbody>
</table>

@include('pdf.partials._letter_branded_close')
</body>
</html>

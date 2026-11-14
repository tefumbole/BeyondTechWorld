<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CBC Health Services, Antivirus Request Form {{ $antivirus->year }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 18px 22px 16px; }
        .banner { width: 100%; height: auto; display: block; margin: 0 0 16px; }
        h2 { font-size: 18px; color: #1d4ed8; margin: 0 0 10px; }
        .bar { display: inline-block; width: 5px; height: 16px; background: #2563eb; margin-right: 8px; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 8px 8px; margin: 0 -8px 6px; }
        td.card { background: #f8fbff; border: 1px solid #dbe7f5; border-radius: 10px; padding: 10px 12px; vertical-align: middle; }
        td.wide { background: #e8f2ff; }
        .label { color: #64748b; font-size: 11px; }
        .value { color: #0f172a; font-size: 14px; font-weight: bold; }
        .ref { font-size: 20px; color: #1d4ed8; }
        .qty { font-size: 28px; color: #1d4ed8; }
        .muted { color: #64748b; font-size: 11px; font-weight: normal; }
        table.signs { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
        td.signcard { border: 1px solid #dbe7f5; border-radius: 12px; padding: 0; vertical-align: top; text-align: center; }
        .signhead { background: #e8f2ff; color: #1d4ed8; font-weight: bold; font-size: 12px; padding: 8px 10px; text-align: center; }
        .signbody { padding: 8px 10px 12px; text-align: center; }
        .sign { width: 150px; height: 58px; display: block; margin: 8px auto; }
        .who { font-size: 12px; font-weight: bold; text-align: center; }
        .role { font-size: 11px; color: #64748b; text-align: center; }
        .rule { height: 4px; background: #2563eb; margin: 16px 0 0; }
        .rule-soft { height: 2px; background: #93c5fd; margin: 3px 40px 0; }
    </style>
</head>
<body>
@if(!empty($logo))<img class="banner" src="{{ $logo }}" alt="CBC Health Services">@endif
<h2><span class="bar"></span>Request details</h2>
<table class="grid">
    <tr>
        <td class="card" colspan="2">
            <div class="label">Reference</div>
            <div class="value ref">{{ $antivirus->reference }}</div>
        </td>
    </tr>
    <tr>
        <td class="card" width="50%">
            <div class="label">Station</div>
            <div class="value">{{ $antivirus->station }}</div>
        </td>
        <td class="card" width="50%">
            <div class="label">Approval date</div>
            <div class="value">{{ $antivirus->approved_at ? $antivirus->approved_at->format('d M Y, H:i') : '—' }}</div>
        </td>
    </tr>
    <tr>
        <td class="card">
            <div class="label">Region</div>
            <div class="value">{{ $antivirus->region }}</div>
        </td>
        <td class="card">
            <div class="label">Year</div>
            <div class="value">{{ $antivirus->year }}</div>
        </td>
    </tr>
    <tr>
        <td class="card wide" colspan="2">
            <div class="label">Approved quantity</div>
            <div class="value qty">{{ $antivirus->quantity }}</div>
        </td>
    </tr>
    @php $requesting = \App\Support\AntivirusCatalog::departmentSummary($antivirus); @endphp
    @if($requesting !== '')
    <tr>
        <td class="card" colspan="2">
            <div class="label">Requesting</div>
            <div class="value">{{ $requesting }}</div>
        </td>
    </tr>
    @endif
    <tr>
        <td class="card" colspan="2">
            <div class="label">Requester</div>
            <div class="value">{{ $antivirus->requester_name }}</div>
            <div class="muted">{{ $antivirus->requesterRoleLabel() }}</div>
        </td>
    </tr>
</table>
<h2><span class="bar"></span>Signatures</h2>
<table class="signs">
    <tr>
        <td class="signcard">
            <div class="signhead">Requester</div>
            <div class="signbody">
                @if($requesterSignature)<img class="sign" src="{{ $requesterSignature }}" alt="">@endif
                <div class="who">{{ $antivirus->requester_name }}</div>
                <div class="role">{{ $antivirus->requesterRoleLabel() }}</div>
                <div class="role">{{ $antivirus->signed_at ? $antivirus->signed_at->format('d M Y, H:i') : '' }}</div>
            </div>
        </td>
        @foreach($approverSignatures as $person)
            @if(!empty($person['image']))
            <td class="signcard">
                <div class="signhead">{{ $person['role'] }}</div>
                <div class="signbody">
                    <img class="sign" src="{{ $person['image'] }}" alt="">
                    <div class="who">{{ $person['name'] }}</div>
                    <div class="role">{{ $person['role'] }}</div>
                    <div class="role">{{ !empty($person['signed_at']) ? $person['signed_at']->format('d M Y, H:i') : '' }}</div>
                </div>
            </td>
            @endif
        @endforeach
        @if($antivirus->chief_signed_at)
        <td class="signcard">
            <div class="signhead">Chief of Administration and Finance</div>
            <div class="signbody">
                @if($chiefSignature)<img class="sign" src="{{ $chiefSignature }}" alt="">@endif
                <div class="who">{{ $antivirus->chief_name }}</div>
                <div class="role">{{ $antivirus->chief_signed_at->format('d M Y, H:i') }}</div>
            </div>
        </td>
        @endif
    </tr>
</table>
<div class="rule"></div>
<div class="rule-soft"></div>
</body>
</html>

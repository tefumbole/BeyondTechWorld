<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CBC Health Services, Antivirus Request Form {{ $antivirus->year }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 24px 32px; }
        .banner { width: 100%; height: auto; display: block; margin: 0 0 18px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        td { padding: 7px 8px; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
        td:first-child { width: 34%; color: #475569; }
        .qty { font-size: 22px; font-weight: bold; color: #0b3f90; }
        .sign { width: 160px; height: 60px; }
        .who { font-size: 11px; color: #475569; }
    </style>
</head>
<body>
@if(!empty($logo))<img class="banner" src="{{ $logo }}" alt="CBC Health Services, Antivirus Request Form {{ $antivirus->year }}">@endif
<table>
    <tr><td>Reference</td><td>{{ $antivirus->reference }}</td></tr>
    <tr><td>Approval date</td><td>{{ $antivirus->approved_at ? $antivirus->approved_at->format('d M Y, H:i') : '' }}</td></tr>
    <tr><td>Station</td><td>{{ $antivirus->station }}</td></tr>
    <tr><td>Region</td><td>{{ $antivirus->region }}</td></tr>
    <tr><td>Year</td><td>{{ $antivirus->year }}</td></tr>
    <tr><td>Approved quantity</td><td class="qty">{{ $antivirus->quantity }}</td></tr>
    <tr><td>Requester</td><td>{{ $antivirus->requester_name }} · {{ $antivirus->requesterRoleLabel() }}</td></tr>
</table>
<p>Signatures</p>
<table>
    <tr>
        <td>
            @if($requesterSignature)<img class="sign" src="{{ $requesterSignature }}" alt="">@endif
            <div>{{ $antivirus->requester_name }}</div>
            <div class="who">{{ $antivirus->requesterRoleLabel() }} · {{ $antivirus->signed_at ? $antivirus->signed_at->format('d M Y, H:i') : '' }}</div>
        </td>
        @foreach($approverSignatures as $person)
            @if(!empty($person['image']))
            <td>
                <img class="sign" src="{{ $person['image'] }}" alt="">
                <div>{{ $person['name'] }}</div>
                <div class="who">{{ $person['role'] }} · {{ !empty($person['signed_at']) ? $person['signed_at']->format('d M Y, H:i') : '' }}</div>
            </td>
            @endif
        @endforeach
        @if($antivirus->chief_signed_at)
        <td>
            @if($chiefSignature)<img class="sign" src="{{ $chiefSignature }}" alt="">@endif
            <div>{{ $antivirus->chief_name }}</div>
            <div class="who">Chief of Administration and Finance · {{ $antivirus->chief_signed_at->format('d M Y, H:i') }}</div>
        </td>
        @endif
    </tr>
</table>
</body>
</html>

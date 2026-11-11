<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Antivirus Licence Request Approval</title>
    @php
        $letterhead_flow = true;
        $use_system_letterhead = true;
    @endphp
    @include('pdf.partials._letter_branded_styles')
    <style>
        h1 { font-size: 18px; color: #0b3f90; margin: 8px 0 12px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        td { padding: 6px 8px; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
        td:first-child { width: 34%; color: #475569; }
        .qty { font-size: 22px; font-weight: bold; color: #0b3f90; }
        .sign { width: 160px; height: 60px; }
        .who { font-size: 11px; color: #475569; }
    </style>
</head>
<body>
@include('pdf.partials._letter_branded_open')
<h1>Antivirus Licence Request Approval</h1>
<table>
    <tr><td>Reference</td><td>{{ $antivirus->reference }}</td></tr>
    <tr><td>Approval date</td><td>{{ $antivirus->approved_at ? $antivirus->approved_at->format('d M Y') : '' }}</td></tr>
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
            <div class="who">{{ $antivirus->requesterRoleLabel() }} · {{ $antivirus->signed_at ? $antivirus->signed_at->format('d M Y') : '' }}</div>
        </td>
        @foreach($approverSignatures as $person)
            <td>
                @if(!empty($person['image']))<img class="sign" src="{{ $person['image'] }}" alt="">@endif
                <div>{{ $person['name'] }}</div>
                <div class="who">{{ $person['role'] }} · {{ !empty($person['signed_at']) ? $person['signed_at']->format('d M Y') : '' }}</div>
            </td>
        @endforeach
        <td>
            @if($chiefSignature)<img class="sign" src="{{ $chiefSignature }}" alt="">@endif
            <div>{{ $antivirus->chief_name }}</div>
            <div class="who">Chief of Administration and Finance · {{ $antivirus->chief_signed_at ? $antivirus->chief_signed_at->format('d M Y') : '' }}</div>
        </td>
    </tr>
</table>
@include('pdf.partials._letter_branded_close')
</body>
</html>

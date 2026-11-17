<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 8mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #10233f; }
h1 { font-size: 13px; margin: 0; color: #0b3f90; }
.muted { color: #5b6b82; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 2px 3px; vertical-align: top; }
.line td { border-bottom: 0.3pt solid #d5deea; }
.net { background: #0b3f90; color: #fff; font-size: 11px; font-weight: bold; padding: 4px; }
.right { text-align: right; }
</style>
</head>
<body>
<table>
<tr>
<td width="18%">@if($company['logo'])<img src="{{ $company['logo'] }}" style="width:48px;">@endif</td>
<td>
<h1>{{ $company['name'] }}</h1>
<div class="muted">{{ $company['address'] }}</div>
<div>NIU / Taxpayer: {{ $company['niu'] ?: '—' }} · CNPS employer: {{ $company['cnps'] ?: '—' }}</div>
</td>
<td class="right">
<strong>BULLETIN DE PAIE / PAYSLIP</strong><br>
{{ $run->period }}<br>
{{ $line->payslip_no }}
</td>
</tr>
</table>
<table style="margin-top:6px;">
<tr>
<td>Employee / Employé<br><strong>{{ $snap['employee'] }}</strong></td>
<td>ID<br><strong>{{ $snap['employee_id'] }}</strong></td>
<td>Category / Catégorie<br><strong>{{ $snap['category'] }}</strong></td>
<td>Department / Service<br><strong>{{ $snap['department'] ?: '—' }}</strong></td>
</tr>
<tr>
<td>CNPS<br><strong>{{ $snap['cnps_number'] ?: '—' }}</strong></td>
<td colspan="3">Payment / Paiement<br><strong>{{ trim(($snap['bank_name'] ?? '').' '.($snap['bank_account'] ?? '').' '.($snap['payment_method'] ?? '')) ?: '—' }}</strong></td>
</tr>
</table>
<table style="margin-top:6px;">
<tr><th align="left">Earnings / Gains</th><th class="right">XAF</th><th align="left">Deductions / Retenues</th><th class="right">XAF</th></tr>
@php $calc = $snap['calculation']; @endphp
<tr class="line"><td>Basic / Salaire de base</td><td class="right">{{ number_format($calc['basic']) }}</td><td>CNPS employee / CNPS salarié 4.2%</td><td class="right">{{ number_format($calc['cnps_employee']) }}</td></tr>
@foreach($calc['earnings'] as $earning)
    @if($earning['code'] !== 'basic' && $earning['amount'] > 0)
    <tr class="line"><td>{{ $earning['label'] }} / {{ $earning['label_fr'] }}</td><td class="right">{{ number_format($earning['amount']) }}</td><td></td><td></td></tr>
    @endif
@endforeach
@if($calc['overtime'] > 0)<tr class="line"><td>Overtime / Heures supplémentaires</td><td class="right">{{ number_format($calc['overtime']) }}</td><td></td><td></td></tr>@endif
@if(!empty($calc['leave_pay']))<tr class="line"><td>Leave pay / Congés</td><td class="right">{{ number_format($calc['leave_pay']) }}</td><td></td><td></td></tr>@endif
<tr class="line"><td></td><td></td><td>IRPP</td><td class="right">{{ number_format($calc['irpp']) }}</td></tr>
<tr class="line"><td></td><td></td><td>CAC 10%</td><td class="right">{{ number_format($calc['cac']) }}</td></tr>
<tr class="line"><td></td><td></td><td>CFC 1%</td><td class="right">{{ number_format($calc['cfc_employee']) }}</td></tr>
<tr class="line"><td></td><td></td><td>RAV / CRTV</td><td class="right">{{ number_format($calc['rav']) }}</td></tr>
<tr class="line"><td></td><td></td><td>TDL</td><td class="right">{{ number_format($calc['tdl']) }}</td></tr>
@if($calc['advance'] > 0)<tr class="line"><td></td><td></td><td>Advance / Avance</td><td class="right">{{ number_format($calc['advance']) }}</td></tr>@endif
@if(!empty($calc['other_deduction']))<tr class="line"><td></td><td></td><td>Other / Autre retenue</td><td class="right">{{ number_format($calc['other_deduction']) }}</td></tr>@endif
<tr class="line"><td><strong>Gross / Brut</strong></td><td class="right"><strong>{{ number_format($calc['gross']) }}</strong></td><td><strong>Total deductions</strong></td><td class="right"><strong>{{ number_format($calc['total_deductions']) }}</strong></td></tr>
</table>
<div class="net">Net salary / Salaire net : {{ number_format($calc['net']) }} XAF</div>
<div style="margin-top:3px;">{{ $snap['words_en'] }}<br>{{ $snap['words_fr'] }}</div>
<div style="margin-top:4px;" class="muted">
Employer / Employeur — CNPS pension {{ number_format($calc['employer_cnps_pension']) }},
family {{ number_format($calc['employer_family']) }},
accident {{ number_format($calc['employer_accident']) }},
CFC {{ number_format($calc['employer_cfc']) }},
FNE {{ number_format($calc['employer_fne']) }}.
Employer cost / Coût employeur {{ number_format($calc['employer_cost']) }} XAF.
</div>
<table style="margin-top:8px;">
<tr>
<td>Prepared / Établi — status {{ $run->status }}@if($run->approved_at)<br>Approved {{ $run->approved_at }}@endif<br><br>Authorized signature / Signature autorisée<br>____________________</td>
<td class="right">@if(!empty($qr))<img src="{{ $qr }}" style="width:58px;height:58px;"><br>@endif Verify / Vérifier<br>{{ $line->verification_code }}<br>{{ $verify }}</td>
</tr>
</table>
</body>
</html>

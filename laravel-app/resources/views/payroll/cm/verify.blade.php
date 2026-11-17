<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Payslip {{ $line->payslip_no }}</title></head>
<body style="font-family:DejaVu Sans,sans-serif;color:#10233f;padding:32px;">
<h2>{{ $company['name'] }}</h2>
<p>Payslip {{ $line->payslip_no }} for {{ $name }}, {{ $run->period }}, is an authentic {{ $run->status }} payroll document.</p>
<p>The salary breakdown is not shown on this public page.</p>
</body></html>

<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Payroll {{ $period }}</title></head>
<body style="font-family:DejaVu Sans,sans-serif;font-size:11px;">
<h2>{{ $company['name'] }} — Payroll {{ $period }}</h2>
<table width="100%" cellspacing="0" cellpadding="4" border="1">
<tr><th>Employee</th><th>Category</th><th>Gross</th><th>Net</th><th>Employer cost</th><th>Status</th></tr>
@foreach($rows as $row)
<tr>
<td>{{ $row['employee'] }}</td>
<td>{{ $row['category'] }}</td>
<td>{{ number_format($row['calculation']['gross']) }}</td>
<td>{{ number_format($row['calculation']['net']) }}</td>
<td>{{ number_format($row['calculation']['employer_cost']) }}</td>
<td>{{ $row['run_status'] }}</td>
</tr>
@endforeach
</table>
</body></html>

@extends('payroll.cm.layout')
@section('payroll')
<form method="get" class="form-inline mb-3">
    <input type="month" name="period" value="{{ $period }}" class="form-control mr-2">
    <button class="btn btn-primary mr-2">Show</button>
    <a class="btn btn-outline-secondary mr-2" href="{{ route('hr.payroll.reports.excel', ['period' => $period]) }}">Excel</a>
    <a class="btn btn-outline-secondary" href="{{ route('hr.payroll.reports.pdf', ['period' => $period]) }}">PDF</a>
</form>
<table class="table table-sm">
    <thead><tr><th>Employee</th><th>Department</th><th>Category</th><th>Status</th><th>Gross</th><th>CNPS</th><th>IRPP</th><th>Net</th><th>Employer cost</th></tr></thead>
    <tbody>
    @php $gross=0; $net=0; $cnps=0; $irpp=0; $cost=0; @endphp
    @foreach($rows as $row)
        @php
            $calc = $row['calculation'];
            $gross += $calc['gross']; $net += $calc['net']; $cnps += $calc['cnps_employee']; $irpp += $calc['irpp']; $cost += $calc['employer_cost'];
        @endphp
        <tr>
            <td>{{ $row['employee'] }}</td>
            <td>{{ $row['department'] }}</td>
            <td>{{ $row['category'] }}</td>
            <td>{{ $row['run_status'] }}</td>
            <td>{{ number_format($calc['gross']) }}</td>
            <td>{{ number_format($calc['cnps_employee']) }}</td>
            <td>{{ number_format($calc['irpp']) }}</td>
            <td>{{ number_format($calc['net']) }}</td>
            <td>{{ number_format($calc['employer_cost']) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="4">Total</th><th>{{ number_format($gross) }}</th><th>{{ number_format($cnps) }}</th><th>{{ number_format($irpp) }}</th><th>{{ number_format($net) }}</th><th>{{ number_format($cost) }}</th></tr></tfoot>
</table>
@endsection

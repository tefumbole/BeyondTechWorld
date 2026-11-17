@extends('payroll.cm.layout')
@section('payroll')
<table class="table">
    <thead><tr><th>Payslip</th><th>Month</th><th>Net</th><th></th></tr></thead>
    <tbody>
    @forelse($lines as $line)
        <tr>
            <td>{{ $line->payslip_no }}</td>
            <td>{{ $line->period }}</td>
            <td>{{ number_format($line->net) }} XAF</td>
            <td><a href="{{ route('hr.payroll.payslip', $line->id) }}">Download</a></td>
        </tr>
    @empty
        <tr><td colspan="4">No published payslip yet.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection

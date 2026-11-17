@extends('payroll.cm.layout')
@section('payroll')
<table class="table">
    <thead><tr><th>Month</th><th>Status</th><th>Gross</th><th>Net</th><th>Employer cost</th></tr></thead>
    <tbody>
    @foreach($runs as $run)
        <tr>
            <td><a href="{{ route('hr.payroll.show', $run->id) }}">{{ $run->title }}</a></td>
            <td>{{ ucfirst($run->status) }}</td>
            <td>{{ number_format($run->total_gross) }}</td>
            <td>{{ number_format($run->total_net) }}</td>
            <td>{{ number_format($run->total_employer) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection

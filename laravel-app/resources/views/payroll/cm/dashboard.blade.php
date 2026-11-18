@extends('payroll.cm.layout')
@section('payroll')
<div class="row">
    <div class="col-md-4"><div class="card p-3"><strong>{{ $profileCount }}</strong><div>Active salary profiles</div></div></div>
    <div class="col-md-8"><div class="card p-3">Rules in force: {{ $rules['name'] ?? 'Cameroon payroll 2026' }}. Work-accident rate {{ number_format(($rules['cnps_accident_rate'] ?? 0.0175) * 100, 0) }}%.</div></div>
</div>
<table class="table mt-3">
    <thead><tr><th>Month</th><th>Status</th><th>Gross</th><th>Net</th><th>Employer cost</th></tr></thead>
    <tbody>
    @forelse($runs as $run)
        <tr>
            <td><a href="{{ route('hr.payroll.show', $run->id) }}">{{ $run->title }}</a></td>
            <td>{{ ucfirst($run->status) }}</td>
            <td>{{ number_format($run->total_gross) }}</td>
            <td>{{ number_format($run->total_net) }}</td>
            <td>{{ number_format($run->total_employer) }}</td>
        </tr>
    @empty
        <tr><td colspan="5">No payroll yet. Open Generate payroll.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection

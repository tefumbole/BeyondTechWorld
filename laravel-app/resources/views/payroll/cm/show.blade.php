@extends('payroll.cm.layout')
@section('payroll')
<h4>{{ $run->title }} <small class="text-muted">{{ ucfirst($run->status) }}</small></h4>
@if(in_array($run->status, ['calculated', 'draft']))
<form method="post" action="{{ route('hr.payroll.recalculate', $run->id) }}">
    @csrf
@endif
<table class="table table-sm">
    <thead><tr><th>Payslip</th><th>Employee</th><th>Gross</th><th>Net</th><th>Overtime</th><th>Advance</th><th></th></tr></thead>
    <tbody>
    @foreach($lines as $line)
        @php $snap = json_decode($line->snapshot_json, true); $calc = $snap['calculation']; @endphp
        <tr>
            <td>{{ $line->payslip_no }}</td>
            <td>{{ $snap['employee'] }}<div class="text-muted">{{ $snap['category'] }}</div></td>
            <td>{{ number_format($line->gross) }}</td>
            <td><strong>{{ number_format($line->net) }}</strong></td>
            <td>@if(in_array($run->status, ['calculated','draft']))<input name="line[{{ $line->id }}][overtime]" value="{{ $calc['overtime'] }}" class="form-control" style="width:110px;">@else{{ number_format($calc['overtime']) }}@endif</td>
            <td>@if(in_array($run->status, ['calculated','draft']))<input name="line[{{ $line->id }}][advance]" value="{{ $calc['advance'] }}" class="form-control" style="width:110px;">@else{{ number_format($calc['advance']) }}@endif</td>
            <td><a href="{{ route('hr.payroll.payslip', $line->id) }}">A5 PDF</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
<p>Gross {{ number_format($run->total_gross) }} · Net {{ number_format($run->total_net) }} · Employer cost {{ number_format($run->total_employer) }} XAF</p>
@if(in_array($run->status, ['calculated', 'draft']))
    <button class="btn btn-secondary">Recalculate</button>
</form>
<form method="post" action="{{ route('hr.payroll.transition', $run->id) }}" class="d-inline">@csrf<input type="hidden" name="status" value="submitted"><button class="btn btn-primary">Submit for approval</button></form>
@endif
@if($run->status === 'submitted' && $canApprove)
<form method="post" action="{{ route('hr.payroll.transition', $run->id) }}">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-success">Approve</button></form>
@endif
@if($run->status === 'approved')
<form method="post" action="{{ route('hr.payroll.transition', $run->id) }}" class="d-inline">@csrf<input type="hidden" name="status" value="paid"><button class="btn btn-primary">Mark paid</button></form>
@endif
@if(in_array($run->status, ['approved', 'paid']) && $canApprove)
<form method="post" action="{{ route('hr.payroll.revise', $run->id) }}" class="d-inline">@csrf<button class="btn btn-outline-secondary">Open a revision</button></form>
@endif
@endsection

@extends('payroll.cm.layout')
@section('payroll')
<h4>Assign staff salary</h4>
<p class="text-muted">Choose the employee, the salary category, and the take-home amount. CNPS, IRPP, CAC, CFC, RAV and TDL are calculated from that amount. Use <a href="{{ route('hr.payroll.categories') }}">Salary categories</a> to change how the gross is split, and enter any extra deduction such as a loan or advance below.</p>
<form method="post" action="{{ route('hr.payroll.profiles.save') }}" class="card p-3 mb-3">
    @csrf
    <div class="row">
        <div class="col-md-4 mb-2">
            <label>Employee</label>
            <select name="employee_id" class="form-control" required>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label>Salary category</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    @if($category->is_active)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label>Take-home salary (XAF)</label>
            <input name="target_net" class="form-control" required inputmode="numeric" placeholder="1498000">
        </div>
        <div class="col-md-3 mb-2">
            <label>Other deduction (XAF)</label>
            <input name="other_deduction" class="form-control" inputmode="numeric" value="0" placeholder="Loan or advance">
        </div>
        <div class="col-md-3 mb-2"><label>CNPS number</label><input name="cnps_number" class="form-control"></div>
        <div class="col-md-3 mb-2"><label>Bank</label><input name="bank_name" class="form-control"></div>
        <div class="col-md-3 mb-2"><label>Account</label><input name="bank_account" class="form-control"></div>
        <div class="col-md-3 mb-2"><label>Payment method</label><input name="payment_method" class="form-control" placeholder="Bank / MoMo"></div>
    </div>
    <input type="hidden" name="is_active" value="1">
    <button class="btn btn-primary">Save salary</button>
</form>
@forelse($profiles as $profile)
    @php
        $person = isset($people[$profile->employee_id]) ? $people[$profile->employee_id] : ['name' => 'Employee'];
        $category = $categories->firstWhere('id', $profile->category_id);
        $calc = isset($breakdowns[$profile->id]) ? $breakdowns[$profile->id] : null;
    @endphp
    <div class="card p-3 mb-3">
        <strong>{{ $person['name'] }}</strong>
        <span class="text-muted"> · {{ $category ? $category->name : 'Category' }} · take-home {{ number_format($profile->target_net) }} XAF</span>
        @if($calc)
            <table class="table table-sm mt-2 mb-0">
                <tr><td>Basic salary</td><td class="text-right">{{ number_format($calc['basic']) }}</td><td>CNPS employee</td><td class="text-right">{{ number_format($calc['cnps_employee']) }}</td></tr>
                @foreach($calc['earnings'] as $earning)
                    @if($earning['code'] !== 'basic' && $earning['amount'] > 0)
                        <tr><td>{{ $earning['label'] }}</td><td class="text-right">{{ number_format($earning['amount']) }}</td><td></td><td></td></tr>
                    @endif
                @endforeach
                <tr><td><strong>Gross</strong></td><td class="text-right"><strong>{{ number_format($calc['gross']) }}</strong></td><td>IRPP</td><td class="text-right">{{ number_format($calc['irpp']) }}</td></tr>
                <tr><td></td><td></td><td>CAC</td><td class="text-right">{{ number_format($calc['cac']) }}</td></tr>
                <tr><td></td><td></td><td>CFC</td><td class="text-right">{{ number_format($calc['cfc_employee']) }}</td></tr>
                <tr><td></td><td></td><td>RAV</td><td class="text-right">{{ number_format($calc['rav']) }}</td></tr>
                <tr><td></td><td></td><td>TDL</td><td class="text-right">{{ number_format($calc['tdl']) }}</td></tr>
                @if($calc['other_deduction'] > 0)
                    <tr><td></td><td></td><td>Other deduction</td><td class="text-right">{{ number_format($calc['other_deduction']) }}</td></tr>
                @endif
                <tr><td>Employer cost</td><td class="text-right">{{ number_format($calc['employer_cost']) }}</td><td><strong>Net salary</strong></td><td class="text-right"><strong>{{ number_format($calc['net']) }}</strong></td></tr>
            </table>
        @else
            <p class="mb-0 text-muted">This salary could not be calculated. Check the category percentages.</p>
        @endif
    </div>
@empty
    <p>No salary has been assigned yet. Use the form above.</p>
@endforelse
@endsection

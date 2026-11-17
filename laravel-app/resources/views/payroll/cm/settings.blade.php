@extends('payroll.cm.layout')
@section('payroll')
<form method="post" action="{{ route('hr.payroll.settings.save') }}" class="card p-3" style="max-width:640px;">
    @csrf
    <label>Company address</label>
    <input name="company_address" class="form-control mb-2" value="{{ $settings->company_address ?? '' }}">
    <label>Taxpayer number (NIU)</label>
    <input name="niu" class="form-control mb-2" value="{{ $settings->niu ?? '' }}">
    <label>CNPS employer number</label>
    <input name="cnps_employer_no" class="form-control mb-2" value="{{ $settings->cnps_employer_no ?? '' }}">
    <label>Work-accident group</label>
    <select name="accident_rate" class="form-control mb-3">
        @foreach([0.0175 => 'Group A, low risk, 1.75%', 0.025 => 'Group B, medium risk, 2.5%', 0.05 => 'Group C, high risk, 5%'] as $rate => $label)
            <option value="{{ $rate }}" {{ abs((float)($settings->accident_rate ?? 0.0175) - $rate) < 0.0001 ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn btn-primary">Save</button>
</form>
<div class="mt-3">
    <h5>Rules that still need an administrator to confirm</h5>
    <ul>
        @foreach(($rules['sources'] ?? []) as $source)
            <li>{{ $source }}</li>
        @endforeach
    </ul>
</div>
@endsection

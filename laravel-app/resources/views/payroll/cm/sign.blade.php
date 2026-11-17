@extends('payroll.cm.layout')
@section('payroll')
<h4>Take-home salary</h4>
<p class="text-muted">Type the net salary, for example 1,498,000. CNPS, IRPP, CAC, CFC, RAV and TDL are calculated so the take-home stays that amount. Sign, then print the payslip.</p>
<form method="post" action="{{ route('hr.payroll.sign.store') }}" id="payslip-sign">
    @csrf
    <div class="row">
        <div class="col-md-4 mb-2">
            <label>Employee</label>
            <select name="employee_id" class="form-control" required>
                <option value="">Select</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" {{ (int) $employeeId === (int) $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label>Salary category</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    @if($category->is_active)
                        <option value="{{ $category->id }}" {{ $profile && (int) $profile->category_id === (int) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label>Month</label>
            <input type="month" name="period" class="form-control" value="{{ $period }}" required>
        </div>
        <div class="col-md-2 mb-2">
            <label>Take-home (XAF)</label>
            <input name="target_net" class="form-control" required inputmode="numeric" value="{{ $amount ?: '' }}" placeholder="1498000">
        </div>
        <div class="col-md-3 mb-2">
            <label>Other deduction</label>
            <input name="other_deduction" class="form-control" value="{{ $profile ? $profile->other_deduction : 0 }}">
        </div>
        <div class="col-md-3 mb-2"><label>CNPS number</label><input name="cnps_number" class="form-control" value="{{ $profile ? $profile->cnps_number : '' }}"></div>
        <div class="col-md-3 mb-2"><label>Bank</label><input name="bank_name" class="form-control" value="{{ $profile ? $profile->bank_name : '' }}"></div>
        <div class="col-md-3 mb-2"><label>Account</label><input name="bank_account" class="form-control" value="{{ $profile ? $profile->bank_account : '' }}"></div>
    </div>
    <input type="hidden" name="payment_method" value="{{ $profile ? $profile->payment_method : '' }}">
    <button class="btn btn-secondary" name="action" value="calculate">Calculate</button>

    @if($calc)
        <table class="table table-sm mt-3">
            <tr><td>Basic salary</td><td class="text-right">{{ number_format($calc['basic']) }}</td><td>CNPS employee 4.2%</td><td class="text-right">{{ number_format($calc['cnps_employee']) }}</td></tr>
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
            <tr><td>Employer cost</td><td class="text-right">{{ number_format($calc['employer_cost']) }}</td><td><strong>Take-home</strong></td><td class="text-right"><strong>{{ number_format($calc['net']) }}</strong></td></tr>
        </table>
        <p class="mb-2"><strong>Digital signature</strong> — sign in the box, then print.</p>
        <canvas id="sign-pad" width="520" height="140" style="border:1px solid #c5d0e0;border-radius:8px;background:#fff;touch-action:none;max-width:100%;"></canvas>
        <input type="hidden" name="signature_image" id="signature_image">
        <div class="mt-2">
            <button type="button" class="btn btn-link" id="sign-clear">Clear</button>
            <button class="btn btn-primary" name="action" value="print">Sign and print payslip</button>
        </div>
    @endif
</form>
@if($calc)
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('sign-pad');
    var form = document.getElementById('payslip-sign');
    if (!canvas || !window.SignaturePad) return;
    var pad = new SignaturePad(canvas, { minWidth: 0.8, maxWidth: 2.2, penColor: '#0b3f90', backgroundColor: '#ffffff' });
    document.getElementById('sign-clear').addEventListener('click', function () { pad.clear(); });
    form.addEventListener('submit', function (event) {
        var active = document.activeElement;
        if (!active || active.value !== 'print') return;
        if (pad.isEmpty()) {
            event.preventDefault();
            alert('Sign the payslip before printing.');
            return;
        }
        document.getElementById('signature_image').value = pad.toDataURL('image/png');
    });
})();
</script>
@endif
@endsection

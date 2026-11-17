@extends('payroll.cm.layout')
@section('payroll')
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
            <label>Category</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <label>Target take-home salary (XAF)</label>
            <input name="target_net" class="form-control" required inputmode="numeric">
        </div>
        <div class="col-md-3 mb-2"><label>CNPS number</label><input name="cnps_number" class="form-control"></div>
        <div class="col-md-3 mb-2"><label>Bank</label><input name="bank_name" class="form-control"></div>
        <div class="col-md-3 mb-2"><label>Account</label><input name="bank_account" class="form-control"></div>
        <div class="col-md-3 mb-2"><label>Payment method</label><input name="payment_method" class="form-control" placeholder="Bank / MoMo"></div>
    </div>
    <input type="hidden" name="is_active" value="1">
    <button class="btn btn-primary">Save profile</button>
</form>
<table class="table">
    <thead><tr><th>Employee</th><th>Category</th><th>Take-home</th><th>CNPS</th><th>Bank</th></tr></thead>
    <tbody>
    @foreach($profiles as $profile)
        @php $person = $people[$profile->employee_id] ?? ['name' => 'Employee']; $category = $categories->firstWhere('id', $profile->category_id); @endphp
        <tr>
            <td>{{ $person['name'] }}</td>
            <td>{{ $category->name ?? '' }}</td>
            <td>{{ number_format($profile->target_net) }} XAF</td>
            <td>{{ $profile->cnps_number }}</td>
            <td>{{ $profile->bank_name }} {{ $profile->bank_account }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection

@extends('layout.main')
@section('content')
<section class="container-fluid pt-3">
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
    <h3 style="color:#0b3f90;font-weight:800;">Roles and Salaries</h3>
    <p class="text-muted">These roles appear when you add an employee. Daily rates for Technician and the other event jobs are copied from event payments. Monthly pay is the take-home salary used by payroll.</p>
    <form method="post" action="{{ route('hr.pay-roles.import') }}" class="mb-3">@csrf<button class="btn btn-outline-primary">Import event payments</button></form>
    <form method="post" action="{{ route('hr.pay-roles.save') }}" class="card p-3 mb-3">
        @csrf
        <h5>Add a role</h5>
        <div class="row">
            <div class="col-md-4 mb-2"><label>Role</label><input name="name" class="form-control" required placeholder="Eng. Network Engineer"></div>
            <div class="col-md-2 mb-2"><label>Pay</label><select name="pay_type" class="form-control"><option value="daily">Daily</option><option value="monthly">Monthly</option></select></div>
            <div class="col-md-2 mb-2"><label>Daily pay (XAF)</label><input name="daily_rate" class="form-control" value="0"></div>
            <div class="col-md-2 mb-2"><label>Monthly pay (XAF)</label><input name="monthly_salary" class="form-control" value="0"></div>
            <div class="col-md-2 mb-2"><label>Payroll category</label>
                <select name="salary_category_code" class="form-control">
                    <option value="">—</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->code }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <input type="hidden" name="is_active" value="1">
        <button class="btn btn-primary">Save role</button>
    </form>
    @foreach($roles as $role)
        <form method="post" action="{{ route('hr.pay-roles.save') }}" class="card p-3 mb-2">
            @csrf
            <input type="hidden" name="id" value="{{ $role->id }}">
            <div class="row">
                <div class="col-md-3 mb-2"><label>Role</label><input name="name" class="form-control" value="{{ $role->name }}" required></div>
                <div class="col-md-2 mb-2"><label>Pay</label>
                    <select name="pay_type" class="form-control">
                        <option value="daily" {{ $role->pay_type === 'daily' ? 'selected' : '' }}>Daily</option>
                        <option value="monthly" {{ $role->pay_type === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2"><label>Daily pay</label><input name="daily_rate" class="form-control" value="{{ $role->daily_rate }}"></div>
                <div class="col-md-2 mb-2"><label>Monthly pay</label><input name="monthly_salary" class="form-control" value="{{ $role->monthly_salary }}"></div>
                <div class="col-md-2 mb-2"><label>Category</label>
                    <select name="salary_category_code" class="form-control">
                        <option value="">—</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->code }}" {{ $role->salary_category_code === $category->code ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 mb-2"><label>Active</label><input type="checkbox" name="is_active" value="1" {{ $role->is_active ? 'checked' : '' }}></div>
            </div>
            <button class="btn btn-primary btn-sm">Save</button>
        </form>
    @endforeach
</section>
<script>
    $("ul#hrm").siblings('a').attr('aria-expanded','true');
    $("ul#hrm").addClass("show");
    $("#hr-pay-roles-menu").addClass("active");
</script>
@endsection

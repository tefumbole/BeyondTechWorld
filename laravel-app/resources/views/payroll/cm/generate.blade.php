@extends('payroll.cm.layout')
@section('payroll')
<form method="post" action="{{ route('hr.payroll.generate.store') }}">
    @csrf
    <label>Payroll month</label>
    <input type="month" name="period" class="form-control mb-3" value="{{ $period }}" required style="max-width:240px;">
    <table class="table">
        <thead><tr><th></th><th>Employee</th><th>Category</th><th>Take-home</th></tr></thead>
        <tbody>
        @foreach($profiles as $profile)
            @php $person = $people[$profile->employee_id] ?? ['name' => 'Employee']; $category = $categories[$profile->category_id] ?? null; @endphp
            <tr>
                <td><input type="checkbox" name="profiles[]" value="{{ $profile->id }}" checked></td>
                <td>{{ $person['name'] }}</td>
                <td>{{ $category->name ?? '' }}</td>
                <td>{{ number_format($profile->target_net) }} XAF</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <button class="btn btn-primary">Calculate payroll</button>
</form>
@endsection

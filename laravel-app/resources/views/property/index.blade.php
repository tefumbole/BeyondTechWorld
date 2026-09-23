@extends('layout.main')
@section('content')
<section class="wa-hub">
    <div class="container-fluid">
        <h1 class="wa-title">Properties</h1>
        @if(session()->has('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session()->has('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="wa-card">
            <h5>Add property</h5>
            <form method="post" action="{{ route('property.store') }}">
                @csrf
                <input name="name" placeholder="Name" required>
                <input name="code" placeholder="Code" required>
                <input name="address" placeholder="Address">
                <input name="city" placeholder="City">
                <input name="country" placeholder="Country">
                <button class="btn btn-primary btn-sm" type="submit">Save</button>
            </form>
        </div>
        <div class="wa-card">
            <table class="table table-sm">
                <thead><tr><th>Code</th><th>Name</th><th>City</th><th>Active</th></tr></thead>
                <tbody>
                @foreach($properties as $row)
                    <tr><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->city }}</td><td>{{ $row->is_active ? 'Yes' : 'No' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="wa-card">
            <h5>Add unit</h5>
            <form method="post" action="{{ route('property.units.store') }}">
                @csrf
                <input name="property_id" placeholder="Property id" required>
                <input name="code" placeholder="Unit code" required>
                <input name="name" placeholder="Name" required>
                <input name="unit_type" placeholder="Type">
                <input name="rent_amount" placeholder="Rent">
                <button class="btn btn-primary btn-sm" type="submit">Save unit</button>
            </form>
            <table class="table table-sm">
                <thead><tr><th>Property</th><th>Code</th><th>Name</th><th>Status</th><th>Rent</th></tr></thead>
                <tbody>
                @foreach($units as $row)
                    <tr><td>{{ $row->property_id }}</td><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->status }}</td><td>{{ $row->rent_amount }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="wa-card">
            <h5>Open tenancy</h5>
            <form method="post" action="{{ route('property.tenancies.store') }}">
                @csrf
                <input name="customer_id" placeholder="Customer id" required>
                <input name="unit_id" placeholder="Unit id" required>
                <input name="start_date" type="date" required>
                <input name="rent_amount" placeholder="Rent" required>
                <input name="due_day" placeholder="Due day">
                <button class="btn btn-primary btn-sm" type="submit">Save tenancy</button>
            </form>
            <table class="table table-sm">
                <thead><tr><th>Id</th><th>Customer</th><th>Unit</th><th>Status</th><th>Rent</th></tr></thead>
                <tbody>
                @foreach($tenancies as $row)
                    <tr><td>{{ $row->id }}</td><td>{{ $row->customer_id }}</td><td>{{ $row->unit_id }}</td><td>{{ $row->status }}</td><td>{{ $row->rent_amount }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="wa-card">
            <h5>Record rent payment</h5>
            <form method="post" action="{{ route('property.rent.payment') }}">
                @csrf
                <input name="tenancy_id" placeholder="Tenancy id" required>
                <input name="amount" placeholder="Amount" required>
                <input name="reference" placeholder="Reference">
                <button class="btn btn-primary btn-sm" type="submit">Record payment</button>
            </form>
        </div>
    </div>
</section>
@endsection

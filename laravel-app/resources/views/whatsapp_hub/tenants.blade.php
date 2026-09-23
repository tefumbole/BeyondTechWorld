@extends('layout.main')
@section('content')
<section class="wa-hub">
    <div class="container-fluid">
        <h1 class="wa-title">Tenant operations</h1>
        <div class="row">
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Active tenancies</div><p class="wa-stat">{{ $metrics['active_tenancies'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Rent due</div><p class="wa-stat">{{ $metrics['rent_due'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Overdue rent</div><p class="wa-stat">{{ $metrics['rent_overdue'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Maintenance open</div><p class="wa-stat">{{ $metrics['maintenance_open'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Maintenance urgent</div><p class="wa-stat">{{ $metrics['maintenance_urgent'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Awaiting assignment</div><p class="wa-stat">{{ $metrics['maintenance_unassigned'] }}</p></div></div>
        </div>
        <div class="wa-card">
            <h5>Recent maintenance</h5>
            <table class="table table-sm">
                <thead><tr><th>Id</th><th>Tenancy</th><th>Status</th><th>Priority</th></tr></thead>
                <tbody>
                @foreach($maintenance as $row)
                    <tr><td><a href="{{ route('property.maintenance') }}">{{ $row->id }}</a></td><td>{{ $row->tenancy_id }}</td><td>{{ $row->status }}</td><td>{{ $row->priority }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

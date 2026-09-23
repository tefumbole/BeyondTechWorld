@extends('layout.main')
@section('content')
<section class="wa-hub">
    <div class="container-fluid">
        <h1 class="wa-title">Maintenance</h1>
        <div class="wa-card">
            <table class="table table-sm">
                <thead><tr><th>Id</th><th>Unit</th><th>Category</th><th>Priority</th><th>Status</th><th>Staff</th><th></th></tr></thead>
                <tbody>
                @foreach($requests as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->unit_id }}</td>
                        <td>{{ $row->category }}</td>
                        <td>{{ $row->priority }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->assigned_employee_id }}</td>
                        <td>
                            <form method="post" action="{{ route('property.maintenance.assign', $row->id) }}">@csrf
                                <input name="employee_id" placeholder="Employee id" size="8">
                                <button class="btn btn-xs" type="submit">Assign</button>
                            </form>
                            <form method="post" action="{{ route('property.maintenance.status', $row->id) }}">@csrf
                                <input name="status" placeholder="Status" size="12">
                                <button class="btn btn-xs" type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

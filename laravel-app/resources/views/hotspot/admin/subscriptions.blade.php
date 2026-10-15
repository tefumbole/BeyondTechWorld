@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot access</h3>
    @include('hotspot.admin.nav')
    @if($rows->isEmpty())
        <div class="alert alert-info">No one has access yet.</div>
    @else
        <table class="table table-bordered">
            <thead><tr><th>User</th><th>State</th><th>Expires</th><th>Used</th><th>Connected</th><th>Note</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row->username }}</td>
                    <td>{{ $row->state }}</td>
                    <td>{{ $row->expires_at }}</td>
                    <td>{{ \App\Hotspot\HotspotLimits::humanBytes($row->data_used_bytes) }} / {{ $row->data_cap_bytes ? \App\Hotspot\HotspotLimits::humanBytes($row->data_cap_bytes) : 'no cap' }}</td>
                    <td>{{ $row->connected_seconds }}s</td>
                    <td>{{ $row->provision_error }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div></section>
@endsection

@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot routers</h3>
    @include('hotspot.admin.nav')
    <p>Management uses the tunnel address only. Leave the router offline until that tunnel exists. Saving a router does not change its firewall.</p>
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('hotspot.routers.store') }}">
            @csrf
            @include('hotspot.admin.router_fields', ['row' => null])
            <button class="btn btn-primary" type="submit">Save router</button>
        </form>
    </div>
    @forelse($rows as $row)
        <div class="card p-3 mb-2">
            <div class="mb-2"><strong>{{ $row->status }}</strong> @if($row->identity) · {{ $row->identity }} @endif @if($row->ros_version) · ROS {{ $row->ros_version }} @endif @if($row->last_seen_at) · {{ $row->last_seen_at }} @endif</div>
            @if($row->last_error)<div class="text-danger mb-2">{{ $row->last_error }}</div>@endif
            <form method="POST" action="{{ route('hotspot.routers.update', $row->id) }}">
                @csrf
                @include('hotspot.admin.router_fields', ['row' => $row])
                <button class="btn btn-sm btn-primary" type="submit">Update</button>
            </form>
            <form class="mt-2" method="POST" action="{{ route('hotspot.routers.test', $row->id) }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary" type="submit">Test connection</button>
            </form>
        </div>
    @empty
        <div class="alert alert-info">No routers yet. You can save one before it is online.</div>
    @endforelse
</div></section>
@endsection

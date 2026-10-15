@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Active sessions</h3>
    @include('hotspot.admin.nav')
    <p>This list is read from the router. It does not change hotspot, firewall, or NAT settings.</p>
    @if($notice)<div class="alert alert-warning">{{ $notice }} The router is offline until the tunnel answers.</div>@endif
    <form method="GET" class="form-inline mb-3">
        <select class="form-control mr-2" name="router_id" required>
            <option value="">Choose a router</option>
            @foreach($routers as $router)
                <option value="{{ $router->id }}" @if(request('router_id') == $router->id) selected @endif>{{ $router->name }} ({{ $router->status }})</option>
            @endforeach
        </select>
        <button class="btn btn-primary" type="submit">Refresh</button>
    </form>
    @if($sessions->isEmpty())
        <div class="alert alert-info">No active sessions. If the tunnel is down, this stays empty.</div>
    @else
        <table class="table table-bordered">
            <thead><tr><th>User</th><th>MAC</th><th>IP</th><th>Up</th><th>Down</th><th>Time</th><th></th></tr></thead>
            <tbody>
            @foreach($sessions as $session)
                <tr>
                    <td>{{ $session->username }}</td>
                    <td>{{ $session->mac }}</td>
                    <td>{{ $session->framed_ip }}</td>
                    <td>{{ \App\Hotspot\HotspotLimits::humanBytes($session->bytes_in) }}</td>
                    <td>{{ \App\Hotspot\HotspotLimits::humanBytes($session->bytes_out) }}</td>
                    <td>{{ $session->uptime_seconds }}s</td>
                    <td>
                        <form method="POST" action="{{ route('hotspot.sessions.disconnect') }}">
                            @csrf
                            <input type="hidden" name="router_id" value="{{ $session->router_id }}">
                            <input type="hidden" name="username" value="{{ $session->username }}">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Disconnect</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div></section>
@endsection

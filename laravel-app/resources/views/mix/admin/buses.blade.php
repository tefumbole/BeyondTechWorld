@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Bus & routing manager</h3>
    @include('mix.admin.nav')
    <p>DCA groups, subgroups, aux mixes, matrices, and physical outputs stay separate. A bus can point at one preset version for its EQ and dynamics.</p>
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('mix.buses.store') }}">@csrf @include('mix.admin.bus_fields', ['bus' => null])<button class="btn btn-primary" type="submit">Add bus</button></form>
    </div>
    @foreach($rows as $bus)
        <div class="card p-3 mb-2">
            <form method="POST" action="{{ route('mix.buses.update', $bus->id) }}">@csrf @include('mix.admin.bus_fields', ['bus' => $bus])<button class="btn btn-sm btn-primary" type="submit">Update</button></form>
        </div>
    @endforeach
    <p><a href="{{ route('mix.routing') }}">Open the routing diagram</a></p>
</div></section>
@endsection

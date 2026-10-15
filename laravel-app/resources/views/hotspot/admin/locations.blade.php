@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Hotspot locations</h3>
    @include('hotspot.admin.nav')
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('hotspot.locations.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-4 mb-2"><label>Name</label><input class="form-control" name="name" required></div>
                <div class="col-md-4 mb-2"><label>Address</label><input class="form-control" name="address"></div>
                <div class="col-md-3 mb-2"><label>Timezone</label><input class="form-control" name="timezone" value="Africa/Douala"></div>
                <div class="col-md-1 mb-2"><label>Active</label><select class="form-control" name="active"><option value="1">Yes</option><option value="0">No</option></select></div>
            </div>
            <button class="btn btn-primary" type="submit">Save location</button>
        </form>
    </div>
    @forelse($rows as $row)
        <div class="card p-3 mb-2">
            <form method="POST" action="{{ route('hotspot.locations.update', $row->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-2"><input class="form-control" name="name" value="{{ $row->name }}" required></div>
                    <div class="col-md-4 mb-2"><input class="form-control" name="address" value="{{ $row->address }}"></div>
                    <div class="col-md-3 mb-2"><input class="form-control" name="timezone" value="{{ $row->timezone }}"></div>
                    <div class="col-md-1 mb-2"><select class="form-control" name="active"><option value="1" @if($row->active) selected @endif>Yes</option><option value="0" @if(!$row->active) selected @endif>No</option></select></div>
                </div>
                <button class="btn btn-sm btn-primary" type="submit">Update</button>
            </form>
        </div>
    @empty
        <div class="alert alert-info">No locations yet.</div>
    @endforelse
</div></section>
@endsection

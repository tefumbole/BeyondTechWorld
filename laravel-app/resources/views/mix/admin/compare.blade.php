@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Compare {{ $preset->name }}</h3>
    @include('mix.admin.nav')
    <form method="GET" class="form-inline mb-3">
        <select class="form-control mr-2" name="left">
            @foreach($preset->versions as $item)
                <option value="{{ $item->id }}" {{ $left && $left->id === $item->id ? 'selected' : '' }}>Version {{ $item->version_number }} — {{ $item->label }}</option>
            @endforeach
        </select>
        <select class="form-control mr-2" name="right">
            @foreach($preset->versions as $item)
                <option value="{{ $item->id }}" {{ $right && $right->id === $item->id ? 'selected' : '' }}>Version {{ $item->version_number }} — {{ $item->label }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary" type="submit">Compare</button>
    </form>
    <div class="row">
        @foreach(['Left' => $left, 'Right' => $right] as $title => $version)
            <div class="col-md-6">
                <div class="card p-3">
                    <h5>{{ $title }} · version {{ $version ? $version->version_number : '—' }}</h5>
                    @if($version)
                        @include('mix.admin.version_summary', ['version' => $version])
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div></section>
@endsection

@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Show templates</h3>
    @include('mix.admin.nav')
    <p>A show is a complete event sheet. It keeps one mix-template version. Opening it shows that sheet. It does not load the console.</p>
    <div class="card p-3 mb-3">
        <form method="POST" action="{{ route('mix.shows.store') }}">
            @csrf
            @include('mix.admin.show_fields', ['show' => null])
            <button class="btn btn-primary" type="submit">Save show</button>
        </form>
    </div>
    @foreach($rows as $show)
        <div class="card p-3 mb-2">
            <form method="POST" action="{{ route('mix.shows.update', $show->id) }}">
                @csrf
                @include('mix.admin.show_fields', ['show' => $show])
                <button class="btn btn-sm btn-primary" type="submit">Update</button>
                @if($show->published && $show->slug)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('mix.public.show', $show->slug) }}">Public sheet</a>
                @endif
            </form>
        </div>
    @endforeach
</div></section>
@endsection

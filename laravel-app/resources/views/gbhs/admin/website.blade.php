@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Website Manager</h3>
    @include('gbhs.admin.nav')
    @foreach($sections as $section)
        <form class="card p-3 mb-2" method="post" action="{{ route('gbhs.sections.update', $section->id) }}" enctype="multipart/form-data">
            @csrf
            <strong>{{ $section->kind }} · {{ $section->slug }}</strong>
            <input class="form-control mt-2" name="title" value="{{ $section->title }}">
            <textarea class="form-control mt-2" name="body" rows="4">{{ $section->body }}</textarea>
            <input class="mt-2" type="file" name="image">
            <label class="mt-2"><input type="checkbox" name="published" value="1" {{ $section->published ? 'checked' : '' }}> Published</label>
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
    @endforeach
    <form class="card p-3" method="post" action="{{ route('gbhs.news.store') }}">
        @csrf
        <h5>News</h5>
        <input class="form-control" name="title" placeholder="Title" required>
        <textarea class="form-control mt-2" name="body" placeholder="Text"></textarea>
        <button class="btn btn-primary btn-sm mt-2" type="submit">Add news</button>
    </form>
    <form class="card p-3 mt-2" method="post" action="{{ route('gbhs.leaders.store') }}" enctype="multipart/form-data">
        @csrf
        <h5>Leader</h5>
        <input class="form-control" name="name" placeholder="Name" required>
        <input class="form-control mt-2" name="role" placeholder="Role">
        <input class="form-control mt-2" name="phone" placeholder="Phone">
        <input class="form-control mt-2" name="email" placeholder="Email">
        <input class="mt-2" type="file" name="photo">
        <button class="btn btn-primary btn-sm mt-2" type="submit">Add leader</button>
    </form>
    @foreach($leaders as $leader)
        <div class="mt-1">{{ $leader->name }} · {{ $leader->role }}</div>
    @endforeach
    <form class="card p-3 mt-2" method="post" action="{{ route('gbhs.documents.store') }}" enctype="multipart/form-data">
        @csrf
        <h5>Public document</h5>
        <input class="form-control" name="title" required>
        <input class="mt-2" type="file" name="file" required>
        <label><input type="checkbox" name="published" value="1"> Published</label>
        <button class="btn btn-primary btn-sm" type="submit">Upload</button>
    </form>
</div></section>
@endsection

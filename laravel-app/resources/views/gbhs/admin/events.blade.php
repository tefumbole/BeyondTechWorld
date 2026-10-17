@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Events & Golden Jubilee</h3>
    @include('gbhs.admin.nav')
    <form class="card p-3" method="post" action="{{ route('gbhs.events.store') }}">
        @csrf
        <input class="form-control" name="title" placeholder="Title" required>
        <textarea class="form-control mt-2" name="body"></textarea>
        <input class="form-control mt-2" type="datetime-local" name="starts_at">
        <input class="form-control mt-2" name="place" placeholder="Place">
        <label><input type="checkbox" name="published" value="1"> Published</label>
        <button class="btn btn-primary btn-sm" type="submit">Save event</button>
    </form>
    @foreach($events as $event)
        <div class="mt-2">{{ $event->starts_at }} · {{ $event->title }} · {{ $event->place }} · {{ $event->published ? 'published' : 'hidden' }}</div>
    @endforeach
</div></section>
@endsection

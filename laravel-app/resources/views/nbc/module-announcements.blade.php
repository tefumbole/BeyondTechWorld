@extends('nbc.layout')
@section('title', 'Announcements · Praise Team')
@section('content')
    <p class="kicker">Team news</p>
    <h1>Announcements</h1>
    <form class="card" method="POST" action="{{ route('nbc.announcements.store') }}">
        @csrf
        <label for="title">Title</label>
        <input id="title" name="title" required>
        <label for="body">Message</label>
        <textarea id="body" name="body" required></textarea>
        <label><input type="checkbox" name="publish" value="1" checked> Publish on the public site</label>
        <button type="submit">Save</button>
    </form>
    @foreach($rows as $row)
        <div class="row">
            <div class="num">{{ $row->published_at ? 'On' : 'Draft' }}</div>
            <div><strong>{{ $row->title }}</strong><div>{{ $row->body }}</div></div>
        </div>
    @endforeach
@endsection

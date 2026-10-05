@extends('nbc.layout')
@section('title', 'Bylaws · Praise Team')
@section('content')
    <p class="kicker">Version {{ $bylaws->version }}</p>
    <h1>Bylaws</h1>
    <p class="sub">Saving creates the next version. People who already signed keep the version they agreed to.</p>
    <form method="POST" action="{{ route('nbc.bylaws.save') }}">
        @csrf
        <textarea name="body" required>{{ $bylaws->body }}</textarea>
        <button type="submit">Save new version</button>
    </form>
@endsection

@extends('nbc.layout')
@section('title', $announcement->title.' · Praise Team')
@section('content')
    <p class="kicker">Announcement</p>
    <h1>{{ $announcement->title }}</h1>
    <p class="sub">{{ $announcement->published_at ? $announcement->published_at->format('j F Y') : '' }}</p>
    <div class="card bylaws">{{ $announcement->body }}</div>
@endsection

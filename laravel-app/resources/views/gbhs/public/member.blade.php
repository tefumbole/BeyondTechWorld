@extends('gbhs.public.layout')
@section('content')
<h1>Member home</h1>
<div class="card">
    <p>{{ $member->membership_number }}</p>
    <p>{{ optional($application)->full_name }}</p>
</div>
<h2>Events</h2>
@forelse($events as $event)
    <div class="card"><strong>{{ $event->title }}</strong><div class="muted">{{ $event->starts_at }} {{ $event->place }}</div></div>
@empty
    <p>No events published.</p>
@endforelse
<h2>Projects</h2>
@foreach($projects as $project)
    <div class="card">{{ $project->title }} · {{ number_format($raised[$project->id] ?? 0) }} XAF verified</div>
@endforeach
@endsection

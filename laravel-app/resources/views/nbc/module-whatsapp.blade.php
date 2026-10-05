@extends('nbc.layout')
@section('title', 'WhatsApp · Praise Team')
@section('content')
    <p class="kicker">WhatsApp notices</p>
    <h1>Team notices</h1>
    <p class="sub">Notices stay in this portal for the praise team. They are not sent out on WhatsApp from here.</p>
    <form class="card" method="POST" action="{{ route('nbc.whatsapp.store') }}">
        @csrf
        <label for="member_id">To</label>
        <select id="member_id" name="member_id">
            <option value="">All members</option>
            @foreach($people as $person)
                <option value="{{ $person->id }}">{{ $person->name }}</option>
            @endforeach
        </select>
        <label for="body">Notice</label>
        <textarea id="body" name="body" required></textarea>
        <button type="submit">Save notice</button>
    </form>
    @foreach($messages as $message)
        <div class="card">
            <div class="sub">{{ $message->created_at->format('j M Y g:i a') }} · {{ $message->member ? $message->member->name : 'All members' }}</div>
            <div>{{ $message->body }}</div>
        </div>
    @endforeach
@endsection

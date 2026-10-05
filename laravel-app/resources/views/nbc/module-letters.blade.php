@extends('nbc.layout')
@section('title', 'Letters · Praise Team')
@section('content')
    <p class="kicker">Letters</p>
    <h1>Letters</h1>
    <form class="card" method="POST" action="{{ route('nbc.letters.store') }}">
        @csrf
        <label for="title">Title</label>
        <input id="title" name="title" required>
        <label for="recipient_name">Recipient name</label>
        <input id="recipient_name" name="recipient_name">
        <label for="recipient_id">Or a member</label>
        <select id="recipient_id" name="recipient_id">
            <option value="">None</option>
            @foreach($people as $person)
                <option value="{{ $person->id }}">{{ $person->name }}</option>
            @endforeach
        </select>
        <label for="letter_date">Date</label>
        <input id="letter_date" name="letter_date" type="date" value="{{ date('Y-m-d') }}">
        <label for="body">Letter</label>
        <textarea id="body" name="body" required></textarea>
        <button type="submit">Save letter</button>
    </form>
    @foreach($letters as $letter)
        <div class="card">
            <strong>{{ $letter->title }}</strong>
            <div class="sub">{{ $letter->recipient_name }} {{ $letter->letter_date ? $letter->letter_date->format('j M Y') : '' }}</div>
            <div class="bylaws">{{ $letter->body }}</div>
        </div>
    @endforeach
@endsection

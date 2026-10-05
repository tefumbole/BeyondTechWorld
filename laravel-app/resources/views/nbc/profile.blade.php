@extends('nbc.layout')
@section('title', 'What I do · Praise Team')
@section('content')
    <p class="kicker">Profile</p>
    <h1>What I do</h1>
    <form method="POST" action="{{ route('nbc.profile.save') }}">
        @csrf
        <label>Name</label>
        <input value="{{ $member->name }}" disabled>
        <label>Email</label>
        <input value="{{ $member->email }}" disabled>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $member->phone) }}">
        <label for="part">Part or instrument</label>
        <input id="part" name="part" value="{{ old('part', $member->part) }}">
        <label for="about">What you do</label>
        <textarea id="about" name="about">{{ old('about', $member->about) }}</textarea>
        <button type="submit">Save</button>
    </form>
@endsection

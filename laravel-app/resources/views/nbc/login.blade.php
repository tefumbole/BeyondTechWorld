@extends('nbc.layout')
@section('title', 'Sign in · Praise Team')
@section('content')
    <p class="kicker">Praise team</p>
    @if($needsOwner)
        <h1>Create the owner</h1>
        <p class="sub">This account approves members and opens every service. It is created once.</p>
        <form method="POST" action="{{ route('nbc.login.submit') }}">
            @csrf
            <label for="name">Name</label>
            <input id="name" name="name" required>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" minlength="8" required>
            <button type="submit">Create and sign in</button>
        </form>
    @else
        <h1>Sign in</h1>
        <form method="POST" action="{{ route('nbc.login.submit') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>
            <button type="submit">Sign in</button>
        </form>
    @endif
@endsection

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Whats MAI</title>
</head>
<body>
    <h1>Whats MAI</h1>
    @if(session('message'))<p>{{ session('message') }}</p>@endif
    @if(session('not_permitted'))<p>{{ session('not_permitted') }}</p>@endif
    <h2>Create an account</h2>
    <form method="POST" action="{{ url('/mai/register') }}">
        @csrf
        <label>Name <input name="name" required></label>
        <label>Email <input name="email" type="email" required></label>
        <label>Password <input name="password" type="password" required></label>
        <button type="submit">Create account</button>
    </form>
    <h2>Sign in</h2>
    <form method="POST" action="{{ url('/mai/login') }}">
        @csrf
        <label>Email <input name="email" type="email" required></label>
        <label>Password <input name="password" type="password" required></label>
        <button type="submit">Sign in</button>
    </form>
</body>
</html>

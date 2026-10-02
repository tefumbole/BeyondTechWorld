<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company portal sign in</title>
    <style>
        body { font-family: Georgia, serif; background: #f4f7fb; margin: 0; }
        main { max-width: 420px; margin: 48px auto; background: #fff; padding: 24px; border-radius: 12px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        input { width: 100%; padding: 10px; box-sizing: border-box; margin-top: 4px; }
        button { margin-top: 16px; background: #0b3f90; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; }
        .bad { background: #fdecec; padding: 8px 10px; }
    </style>
</head>
<body>
<main>
    <h1>Company portal</h1>
    <p>Sign in to manage your company, subscriptions, and business rules.</p>
    @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
    <form method="POST" action="{{ route('cloud.login.submit') }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit">Sign in</button>
    </form>
    <p><a href="{{ route('cloud.register') }}">Create a company</a></p>
</main>
</body>
</html>

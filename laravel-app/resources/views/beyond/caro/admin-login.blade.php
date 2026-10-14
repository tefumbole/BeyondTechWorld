<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Invitation admin</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f6f3ec; font-family: "Segoe UI", sans-serif; color: #1d2433; padding: 20px; }
        form { width: min(420px, 100%); background: #fff; border-radius: 18px; padding: 22px 18px; box-shadow: 0 12px 30px rgba(20,16,40,.08); }
        h1 { margin: 0 0 6px; font-size: 26px; }
        p { margin: 0 0 14px; color: #66748c; }
        label { display: block; font-weight: 700; margin: 12px 0 6px; }
        input { width: 100%; min-height: 46px; border: 1px solid #e4e0d8; border-radius: 12px; padding: 0 12px; font-size: 16px; }
        button { width: 100%; margin-top: 16px; min-height: 48px; border: 0; border-radius: 12px; background: #14182b; color: #f3d98a; font-weight: 800; font-size: 16px; }
        .err { background: #fdecec; color: #9b1c1c; border-radius: 10px; padding: 8px 10px; font-weight: 700; }
    </style>
</head>
<body>
<form method="POST" action="">
    @csrf
    <h1>Invitation admin</h1>
    <p>Sign in with your Beyond admin account.</p>
    @if($error)<p class="err">{{ $error }}</p>@endif
    <label for="identifier">Email or username</label>
    <input id="identifier" name="identifier" autocomplete="username" required value="{{ old('identifier') }}">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Sign in</button>
</form>
</body>
</html>

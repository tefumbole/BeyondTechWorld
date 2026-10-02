<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create your company</title>
    <style>
        body { font-family: Georgia, serif; background: #f4f7fb; margin: 0; }
        main { max-width: 520px; margin: 32px auto; background: #fff; padding: 24px; border-radius: 12px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        input { width: 100%; padding: 10px; box-sizing: border-box; margin-top: 4px; }
        button { margin-top: 16px; background: #0b3f90; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; }
        .bad { background: #fdecec; padding: 8px 10px; }
    </style>
</head>
<body>
<main>
    <h1>Create your company</h1>
    <p>You get a portal sign-in. Each module includes one 24-hour trial for your phone number. Payment is by MoMo or VISA.</p>
    @if($errors->any())<p class="bad">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('cloud.register.submit') }}">
        @csrf
        <label>Your name</label>
        <input name="name" value="{{ old('name') }}" required>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>
        <label>Phone (used once per module trial)</label>
        <input name="phone" value="{{ old('phone') }}" placeholder="+237677000111" required>
        <label>Company name</label>
        <input name="company_name" value="{{ old('company_name') }}" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required>
        <button type="submit">Create portal</button>
    </form>
</main>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request invitation — {{ optional($link->event)->name }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px;">
    <h1 class="h4 mb-2">Request invitation</h1>
    <p class="text-muted">{{ optional($link->event)->name }} · {{ optional($link->category)->name }}</p>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
    <form method="POST" action="{{ route('online_invitation.request.submit', $link->token) }}" class="card card-body shadow-sm">
        @csrf
        <div class="form-group">
            <label>Full name *</label>
            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div class="form-group">
            <label>WhatsApp phone *</label>
            <input type="text" name="phone" class="form-control" required placeholder="+237..." value="{{ old('phone') }}">
        </div>
        <div class="form-group">
            <label>Email (optional)</label>
            <input type="email" name="email" class="form-control" value="{{ old('email') }}">
        </div>
        <button class="btn btn-primary btn-block" type="submit">Request invitation</button>
    </form>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Get invitation — {{ optional($link->event)->name }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <style>
        body { background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); min-height: 100vh; }
        .card-apply {
            border: 0; border-radius: 16px; box-shadow: 0 10px 30px rgba(15,23,42,.08);
        }
        .brand { color: #0b3f90; font-weight: 800; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width:480px;">
    <div class="text-center mb-4">
        <div class="brand h4 mb-1">Beyond Tech World</div>
        <h1 class="h5 mb-1">Get your invitation</h1>
        <p class="text-muted mb-0">{{ optional($link->event)->name }}</p>
        <p class="text-muted small">{{ optional($link->category)->name }}</p>
    </div>

    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('online_invitation.request.submit', $link->token) }}" class="card card-apply card-body">
        @csrf
        <p class="text-muted small">Enter your WhatsApp number. We will resolve your name and send the invitation to this number.</p>
        <div class="form-group mb-3">
            <label for="phone">WhatsApp phone *</label>
            <input type="tel" name="phone" id="phone" class="form-control form-control-lg" required
                   placeholder="e.g. 675321739 or +237675321739"
                   value="{{ old('phone') }}" autocomplete="tel">
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit" style="background:#0b3f90;border-color:#0b3f90;">
            Send my invitation
        </button>
    </form>
</div>
</body>
</html>

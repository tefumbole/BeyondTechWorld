@extends('nbc.layout')
@section('title', 'Join · Praise Team')
@section('content')
    <p class="kicker">Membership</p>
    <h1>Join the praise team</h1>
    <p class="sub">Read the bylaws. If you agree, sign, and a leader will approve your account.</p>
    <div class="card bylaws">{{ $bylaws->body }}</div>
    <p class="sub">Bylaws version {{ $bylaws->version }}</p>
    <form method="POST" action="{{ route('nbc.join.store') }}" id="join-form">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone') }}">
        <label for="part">Part or instrument</label>
        <input id="part" name="part" value="{{ old('part') }}" placeholder="Soprano, keys, drums">
        <label for="about">What you do</label>
        <textarea id="about" name="about">{{ old('about') }}</textarea>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required minlength="8">
        <label><input type="checkbox" name="agree" value="1" {{ old('agree') ? 'checked' : '' }} required> I have read the bylaws and I agree.</label>
        <label>Signature</label>
        <canvas id="pad" width="800" height="200"></canvas>
        <input type="hidden" name="signature" id="signature">
        <div><button type="button" id="clear" class="btn" style="background:#5c5348">Clear signature</button></div>
        <button type="submit">Submit application</button>
    </form>
    <script>
        (function () {
            var canvas = document.getElementById('pad');
            var hidden = document.getElementById('signature');
            var context = canvas.getContext('2d');
            var drawing = false;
            function point(event) {
                var rect = canvas.getBoundingClientRect();
                var source = event.touches ? event.touches[0] : event;
                return {
                    x: (source.clientX - rect.left) * (canvas.width / rect.width),
                    y: (source.clientY - rect.top) * (canvas.height / rect.height)
                };
            }
            var signed = false;
            function start(event) {
                drawing = true;
                signed = true;
                var p = point(event);
                context.beginPath();
                context.moveTo(p.x, p.y);
                event.preventDefault();
            }
            function move(event) {
                if (!drawing) return;
                var p = point(event);
                context.lineWidth = 2;
                context.lineCap = 'round';
                context.strokeStyle = '#1c160e';
                context.lineTo(p.x, p.y);
                context.stroke();
                event.preventDefault();
            }
            function end() { drawing = false; }
            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            canvas.addEventListener('touchstart', start, {passive: false});
            canvas.addEventListener('touchmove', move, {passive: false});
            window.addEventListener('touchend', end);
            document.getElementById('clear').addEventListener('click', function () {
                context.clearRect(0, 0, canvas.width, canvas.height);
                signed = false;
            });
            document.getElementById('join-form').addEventListener('submit', function (event) {
                if (!signed) {
                    event.preventDefault();
                    alert('Sign in the box before you continue.');
                    return;
                }
                hidden.value = canvas.toDataURL('image/png');
            });
        })();
    </script>
@endsection

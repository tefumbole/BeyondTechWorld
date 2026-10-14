<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>MSATA invitations</title>
    <style>
        body { margin: 0; background: #f6f3ec; color: #1d2433; font-family: "Segoe UI", sans-serif; }
        header { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 16px; background: #14182b; color: #fff; }
        h1 { margin: 0; font-size: 22px; }
        header form { margin: 0; }
        header button, .danger, .save { min-height: 40px; border-radius: 10px; font-weight: 800; cursor: pointer; }
        header button { background: transparent; color: #f3d98a; border: 1px solid #f3d98a; padding: 0 12px; }
        main { width: min(980px, calc(100% - 20px)); margin: 16px auto 32px; }
        .notice { background: #f8f1df; color: #6b5420; border-radius: 12px; padding: 10px 12px; font-weight: 700; }
        .find { display: flex; gap: 8px; margin: 12px 0; }
        .find input { flex: 1; min-height: 44px; border: 1px solid #e4e0d8; border-radius: 12px; padding: 0 12px; font-size: 16px; }
        .find button, .save { border: 0; background: #14182b; color: #f3d98a; padding: 0 14px; }
        .card { background: #fff; border-radius: 16px; padding: 14px; margin: 0 0 12px; box-shadow: 0 8px 20px rgba(20,16,40,.06); }
        .top { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; }
        h2 { margin: 0; font-size: 18px; }
        .seat { color: #8a6a24; font-weight: 800; }
        .meta { color: #66748c; font-size: 14px; margin: 6px 0; }
        .row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .row input { min-height: 42px; border: 1px solid #e4e0d8; border-radius: 10px; padding: 0 10px; font-size: 16px; width: 140px; }
        .danger { background: #fff; color: #9b1c1c; border: 1px solid #e7b4b4; padding: 0 12px; }
        .empty { color: #66748c; }
        .pill { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 800; background: #eef3fb; }
        .pill.on { background: #e7f6ee; color: #146c43; }
    </style>
</head>
<body>
<header>
    <h1>MSATA invitations</h1>
    <form method="POST" action="{{ $logoutUrl }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
</header>
<main>
    @if($notice)<p class="notice">{{ $notice }}</p>@endif
    <form class="find" method="GET">
        <input name="q" value="{{ $q }}" placeholder="Search name, phone, or seat" aria-label="Search invitations">
        <button type="submit">Search</button>
    </form>
    @forelse($rows as $row)
        <article class="card">
            <div class="top">
                <h2>{{ $row->name }}</h2>
                <span class="seat">{{ $row->seatLabel() }}</span>
            </div>
            <p class="meta">{{ $row->roleLabel() }} · {{ $row->phone }} · {{ optional($row->created_at)->format('d M Y H:i') }}</p>
            <p class="meta">
                <span class="pill {{ $row->presented_at ? 'on' : '' }}">{{ $row->presented_at ? 'Present' : 'Not present' }}</span>
                <span class="pill">{{ $row->invitation_sent_at ? 'Invitation sent' : 'Invitation not sent yet' }}</span>
            </p>
            <div class="row">
                <form method="POST" action="{{ url()->current() }}/{{ $row->id }}/seat">
                    @csrf
                    <input name="seat" value="{{ $row->seatLabel() }}" aria-label="Seat for {{ $row->name }}" required pattern="[A-Da-d]-[0-9]{2}-[0-9]{2}">
                    <button class="save" type="submit">Save seat</button>
                </form>
                <form method="POST" action="{{ url()->current() }}/{{ $row->id }}/delete" onsubmit="return confirm('Remove this invitation?');">
                    @csrf
                    <button class="danger" type="submit">Delete</button>
                </form>
            </div>
        </article>
    @empty
        <p class="empty">No invitations yet.</p>
    @endforelse
</main>
</body>
</html>

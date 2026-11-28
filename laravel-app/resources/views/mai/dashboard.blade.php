<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Whats MAI dashboard</title>
    <style>
        body { margin: 0; background: #041833; color: #fff; font-family: Georgia, serif; }
        main { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 2rem; margin: 0 0 8px; }
        a { color: #F5D76E; }
        .totals, .cards { display: grid; gap: 12px; }
        .totals { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
        .card, .row, form.filters { background: #0b3f90; border: 1px solid #D4AF37; border-radius: 16px; padding: 14px; }
        .label { color: #F5D76E; font-size: 0.8rem; letter-spacing: .04em; }
        .num { font-size: 1.8rem; font-weight: 700; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 8px; background: #072f6b; color: #fff; font-size: 0.75rem; }
        .badge.is-on { background: #146c43; }
        .badge.is-off { background: #7f1d1d; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid rgba(212,175,55,.35); font-size: 0.92rem; }
        input, select, button { font: inherit; border-radius: 999px; border: 0; padding: 8px 12px; }
        button { background: #D4AF37; color: #072f6b; font-weight: 700; cursor: pointer; }
        .filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0; }
        .note { background: rgba(212,175,55,.16); border-radius: 12px; padding: 10px 12px; }
    </style>
</head>
<body>
<main>
    <p class="label">WHATS MAI</p>
    <h1>Operator dashboard</h1>
    @if(session('message'))<p class="note">{{ session('message') }}</p>@endif
    <section class="totals">
        <div class="card"><div class="label">Registered</div><div class="num">{{ $summary['registered'] }}</div></div>
        <div class="card"><div class="label">Signed in</div><div class="num">{{ $summary['signed_in'] }}</div></div>
        <div class="card"><div class="label">Paid</div><div class="num">{{ $summary['paid'] }}</div></div>
        <div class="card"><div class="label">Trials</div><div class="num">{{ $summary['trials'] }}</div></div>
        <div class="card"><div class="label">Connected</div><div class="num">{{ $summary['connected'] }}</div></div>
        <div class="card"><div class="label">Signed in, no plan</div><div class="num">{{ $summary['signed_in_without_plan'] }}</div></div>
    </section>
    <form class="filters" method="GET" action="{{ route('whatsapp.mai.dashboard') }}">
        <input name="q" value="{{ $q }}" placeholder="Search name, email, or phone">
        <select name="filter">
            @foreach(['all' => 'All', 'no_session' => 'No session', 'trial' => 'Trial', 'paid' => 'Paid', 'expired' => 'Expired', 'disconnected' => 'Disconnected', 'signed_in' => 'Signed in'] as $value => $label)
                <option value="{{ $value }}" {{ $filter === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit">Refresh</button>
    </form>
    <div class="card">
        <table>
            <thead><tr><th>Account</th><th>Last sign-in</th><th>Plan</th><th>Status</th><th>Expires</th><th>Sessions</th><th></th></tr></thead>
            <tbody>
            @forelse($list['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}<br>{{ $row['email'] }}</td>
                    <td>{{ $row['signed_in_at'] ?: '—' }}</td>
                    <td>{{ $row['plan'] }}</td>
                    <td><span class="badge">{{ $row['plan_status'] }}</span></td>
                    <td>{{ $row['expires'] ?: '—' }}</td>
                    <td>{{ $row['connected'] }}/{{ $row['sessions'] }}@if($row['session_limit']) of {{ $row['session_limit'] }}@endif</td>
                    <td><a href="{{ route('whatsapp.mai.dashboard', ['account' => $row['id'], 'q' => $q, 'filter' => $filter]) }}">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No Whats MAI accounts match.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if($list['page'] > 1)<a href="{{ route('whatsapp.mai.dashboard', ['page' => $list['page'] - 1, 'q' => $q, 'filter' => $filter]) }}">Previous</a>@endif
        @if($list['page'] * 20 < $list['total'])<a href="{{ route('whatsapp.mai.dashboard', ['page' => $list['page'] + 1, 'q' => $q, 'filter' => $filter]) }}">Next</a>@endif
    </div>
    @if($account)
        <h2>{{ $account['name'] }}</h2>
        <p>Last sign-in: {{ $account['signed_in_at'] ?: 'None' }}. Plan {{ $account['plan'] }} is {{ $account['plan_status'] }}@if($account['expires']) until {{ $account['expires'] }}@endif.</p>
        <div class="cards">
            @foreach($account['sessions'] as $session)
                <article class="card">
                    <strong>{{ $session['name'] }}</strong>
                    <p>Session ID {{ $session['public_id'] }}</p>
                    <p>Phone {{ $session['phone'] }} · Owner {{ $session['owner'] }} · {{ $session['provider'] }}</p>
                    <p>
                        <span class="badge {{ $session['connection'] === 'CONNECTED' ? 'is-on' : 'is-off' }}">{{ $session['connection'] }}</span>
                        <span class="badge">Plan {{ $session['subscription'] }}</span>
                        <span class="badge {{ $session['api_sending'] ? 'is-on' : 'is-off' }}">API {{ $session['api_sending'] ? 'ready' : 'not ready' }}</span>
                    </p>
                    <p>Last activity {{ $session['activity'] ?: '—' }}</p>
                </article>
            @endforeach
        </div>
        <h3>API tokens</h3>
        <div class="card">
            <table>
                <thead><tr><th>Label</th><th>Prefix</th><th>Scope</th><th>Created</th><th>Last used</th><th>Revoked</th><th></th></tr></thead>
                <tbody>
                @forelse($account['tokens'] as $token)
                    <tr>
                        <td>{{ $token['label'] }}</td>
                        <td>{{ $token['prefix'] }}</td>
                        <td>{{ $token['scope'] }}</td>
                        <td>{{ $token['created'] }}</td>
                        <td>{{ $token['last_used'] ?: '—' }}</td>
                        <td>{{ $token['revoked'] ?: 'Active' }}</td>
                        <td>
                            @if($token['revoked'] === '')
                                <form method="POST" action="{{ route('whatsapp.mai.dashboard.revoke', $token['id']) }}">@csrf<button type="submit">Revoke</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No tokens.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</main>
</body>
</html>

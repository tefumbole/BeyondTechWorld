<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meals — Induction Service</title>
    <style>
        body { margin: 0; font-family: "Source Sans Pro", sans-serif; background: #071433; color: #fffaf1; }
        .wrap { width: min(860px, calc(100% - 20px)); margin: 0 auto; padding: 24px 0 40px; }
        h1 { font-family: Georgia, serif; color: #f3dd8a; }
        a { color: #f3dd8a; }
        table { width: 100%; border-collapse: collapse; background: rgba(7,20,51,.55); }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid rgba(212,175,55,.3); }
        th { color: #f3dd8a; font-size: 13px; }
        .yes { color: #86efac; font-weight: 700; }
        button { margin-top: 16px; border: 0; border-radius: 999px; background: #d4af37; color: #0b245c; min-height: 46px; padding: 0 18px; font-weight: 700; cursor: pointer; }
        p { color: #e8eef8; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Who ate</h1>
    <p>{{ $eaten->count() }} eaten · {{ $invitations->count() }} invitations</p>
    @if(request()->has('thanks'))
        <p>Thank-you messages are going out to {{ (int) request('thanks') }} {{ (int) request('thanks') === 1 ? 'person' : 'people' }}, one every 5 seconds.</p>
    @endif
    <table>
        <thead>
            <tr><th>Ticket</th><th>Name</th><th>Type</th><th>Eaten</th><th>Attended</th></tr>
        </thead>
        <tbody>
            @foreach($invitations as $row)
                <tr>
                    <td>{{ $row->ticket_code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->typeLabel() }}</td>
                    <td class="{{ $row->eaten_at ? 'yes' : '' }}">{{ $row->eaten_at ? $row->eaten_at->timezone('Africa/Douala')->format('H:i') : '—' }}</td>
                    <td class="{{ $row->attended_at ? 'yes' : '' }}">{{ $row->attended_at ? $row->attended_at->timezone('Africa/Douala')->format('H:i') : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <form method="POST" action="{{ url('/yong/meals/thanks') }}" onsubmit="return confirm('Send the thank-you to everyone who ate or attended?');">
        @csrf
        <button type="submit">Send thank-you</button>
    </form>
    <p><a href="{{ url('/yong') }}">Back to the invitation page</a></p>
</div>
</body>
</html>

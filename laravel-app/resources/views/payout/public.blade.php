<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request for Payment</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f6fb; color: #1f2a44; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 24px 16px 80px; }
        h1 { font-size: 24px; margin: 0 0 8px; }
        p { color: #6f7b91; }
        .card { background: #fff; border: 1px solid #e3e9f4; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        label { display: block; font-size: 13px; margin-bottom: 4px; }
        input[type="text"], input[type="search"], input[type="number"], input[type="tel"] { width: 100%; border: 1px solid #d5deee; border-radius: 8px; padding: 10px 12px; font-size: 16px; }
        .row { display: flex; gap: 12px; }
        .row > div { flex: 1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 4px; border-bottom: 1px solid #e3e9f4; vertical-align: middle; }
        th { font-size: 13px; color: #6f7b91; }
        .btn { background: #0b3f90; color: #fff; border: 0; border-radius: 8px; padding: 10px 16px; font-size: 16px; cursor: pointer; }
        .plus { width: 44px; height: 44px; border-radius: 22px; font-size: 24px; line-height: 1; }
        .ghost { background: #e7eef8; color: #0b3f90; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 12px; }
        .ok { background: #e7f6ee; color: #146c43; }
        .bad { background: #fdecec; color: #9b1c1c; }
        .bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .amt { max-width: 140px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Request for Payment</h1>
    <p>Choose the people, enter each amount, and submit. The payment is sent for approval. Money is not taken now.</p>
    @if(session('message'))<div class="alert ok">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert bad">{{ session('not_permitted') }}</div>@endif

    <form method="GET" class="card bar">
        <input type="search" name="q" value="{{ $q }}" placeholder="Search a name or number">
        <button class="btn" type="submit">Search</button>
    </form>

    <form method="POST" action="{{ route('payout.request.store', ['token' => $token]) }}" id="requestForm">
        @csrf
        <div class="card">
            <div class="row">
                <div>
                    <label>Your name</label>
                    <input type="text" name="requester_name" maxlength="80" required value="{{ old('requester_name') }}">
                </div>
                <div>
                    <label>Note</label>
                    <input type="text" name="note" maxlength="180" placeholder="What this payment is for" value="{{ old('note') }}">
                </div>
            </div>
        </div>
        <div class="card" style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Customer</th>
                        <th>Number</th>
                        <th>Amount (XAF)</th>
                    </tr>
                </thead>
                <tbody id="peopleBody">
                    @forelse($people as $person)
                        <tr>
                            <td><input type="checkbox" class="pick" name="customer_id[]" value="{{ $person->id }}"></td>
                            <td>{{ $person->name }}</td>
                            <td>{{ $person->phone_number }}</td>
                            <td><input class="amt" type="number" name="amount[{{ $person->id }}]" min="100" max="1000000" step="1" placeholder="Amount" disabled></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No customers match that search. Add a number with the plus button.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="bar" style="margin-top:12px">
                <button class="btn plus" type="button" id="addPhone" title="Add a phone number">+</button>
                <span id="requestTotal"></span>
            </div>
        </div>
        <button class="btn" type="submit">Submit request</button>
    </form>
</div>
<script>
(function () {
    var form = document.getElementById('requestForm');
    var body = document.getElementById('peopleBody');
    var total = document.getElementById('requestTotal');
    function picks() { return form.querySelectorAll('.pick'); }
    function extras() { return form.querySelectorAll('.extra-amount'); }
    function refresh() {
        var count = 0;
        var sum = 0;
        picks().forEach(function (box) {
            var input = form.querySelector('[name="amount[' + box.value + ']"]');
            if (input) input.disabled = !box.checked;
            if (!box.checked) return;
            count += 1;
            sum += input ? (parseInt(input.value, 10) || 0) : 0;
        });
        extras().forEach(function (input) {
            var phone = input.parentNode.parentNode.querySelector('.extra-phone');
            if (!phone || phone.value.trim() === '') return;
            count += 1;
            sum += parseInt(input.value, 10) || 0;
        });
        total.textContent = count ? count + ' people · ' + sum.toLocaleString() + ' XAF' : '';
    }
    picks().forEach(function (box) { box.addEventListener('change', refresh); });
    form.querySelectorAll('.amt').forEach(function (input) { input.addEventListener('input', refresh); });
    document.getElementById('addPhone').addEventListener('click', function () {
        var row = document.createElement('tr');
        row.innerHTML = '<td></td><td colspan="2"><input class="extra-phone" type="tel" name="extra_phone[]" placeholder="Phone number, 6xxxxxxxx"></td><td><input class="extra-amount amt" type="number" name="extra_amount[]" min="100" max="1000000" step="1" placeholder="Amount"></td>';
        body.appendChild(row);
        row.querySelector('.extra-phone').addEventListener('input', refresh);
        row.querySelector('.extra-amount').addEventListener('input', refresh);
        row.querySelector('.extra-phone').focus();
    });
    form.addEventListener('submit', function (event) {
        var ready = false;
        picks().forEach(function (box) { if (box.checked) ready = true; });
        extras().forEach(function (input) {
            var phone = input.parentNode.parentNode.querySelector('.extra-phone');
            if (phone && phone.value.trim() !== '') ready = true;
        });
        if (!ready) event.preventDefault();
    });
})();
</script>
</body>
</html>

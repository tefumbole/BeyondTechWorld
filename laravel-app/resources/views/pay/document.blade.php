<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay {{ $link->reference_no }}</title>
    <style>
        :root { --primary:#0b3f90; --accent:#c6ab47; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: Nunito, system-ui, sans-serif; background:#f3f6fb; color:#1f2a44; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 18px 12px 40px; }
        .card { background:#fff; border-radius:22px; overflow:hidden; box-shadow:0 12px 32px rgba(7,47,107,.12); }
        .head { background:#0b3f90; color:#fff; padding:16px 18px 18px; position:relative; }
        .kicker { color:var(--accent); font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .who { margin-top:4px; font-size:22px; font-weight:800; line-height:1.15; }
        .ref { color:rgba(255,255,255,.75); font-size:13px; }
        .body { background:#f3f6fb; padding:16px; }
        .due { background:#fff; border-radius:16px; padding:14px 16px; margin-bottom:12px; }
        .due span { display:block; color:var(--accent); font-size:12px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        .due strong { display:block; font-size:32px; line-height:1.1; color:var(--primary); }
        label { display:block; margin:8px 0 6px; color:#6f7b91; font-size:12px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        input { width:100%; min-height:46px; border:1px solid #d5deee; border-radius:12px; padding:8px 12px; font-size:16px; }
        .holder { min-height:22px; margin:6px 0 0; font-weight:800; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:12px 0; }
        button.choice { min-height:72px; border-radius:16px; border:1px solid #d5deee; background:#fff; color:#1f2a44; text-align:center; padding:10px; }
        button.choice strong, button.choice small { display:block; }
        button.choice small { color:#6f7b91; font-weight:700; }
        button.choice.on { background:#0b3f90; border-color:#0b3f90; color:#fff; }
        button.choice.on small { color:var(--accent); }
        button.choice.wide { grid-column:1 / -1; }
        table { width:100%; background:#fff; border-radius:12px; border-collapse:collapse; margin-bottom:8px; }
        th, td { padding:6px; text-align:left; }
        .left, .hint { color:#6f7b91; margin:8px 0 0; }
        .submit { width:100%; min-height:48px; border:0; border-radius:999px; background:var(--accent); color:#072f6b; font-weight:800; font-size:16px; }
        .foot { background:#fff; padding:12px 16px 16px; }
        .alert { margin-bottom:12px; padding:12px 14px; border-radius:12px; }
        .ok { background:#e8f7ee; color:#146c36; }
        .no { background:#fdecec; color:#9b1c1c; }
        .wait { background:#e8eef8; color:#0b3f90; }
        .plus { width:44px; height:44px; border:1px solid var(--accent); border-radius:22px; background:#fff; color:var(--primary); font-size:24px; }
        a.visa { display:block; text-align:center; margin-top:10px; color:var(--primary); }
    </style>
</head>
<body>
<div class="wrap">
    @if(session('not_permitted'))<div class="alert no">{{ session('not_permitted') }}</div>@endif
    <div class="card">
        <div class="head">
            <div class="kicker">{{ $link->kind === 'sale' ? 'Invoice' : 'Quotation' }}</div>
            <div class="who">{{ $link->person_name }}</div>
            <div class="ref">{{ $link->reference_no }}@if($link->requested_by) · Requested by {{ $link->requested_by }}@endif</div>
        </div>
        <div class="body">
            <div class="due">
                <span>Still due</span>
                <strong id="due">{{ number_format($link->amount, 0, '.', ' ') }} XAF</strong>
            </div>
            @if($link->status === 'paid')
                <div class="alert ok" id="paidNote">Payment received. The invoice has been sent.</div>
            @elseif($link->status === 'pending')
                <div class="alert wait" id="waitNote">A request is on the phone. Enter the PIN there to approve it. Stay on this page.</div>
                @foreach($pendingLines as $line)
                    <div class="holder">{{ $line['phone'] }} · {{ number_format($line['amount'], 0, '.', ' ') }} XAF</div>
                @endforeach
            @else
                @if($link->status === 'failed')
                    <div class="alert no">{{ $link->error ?: 'The payment was not approved.' }}</div>
                @endif
                <form method="POST" action="{{ route('document.pay.submit', ['token' => $link->token]) }}" id="payForm">
                    @csrf
                    <input type="hidden" name="pay_with" id="payWith" value="momo">
                    <input type="hidden" name="pay_mode" id="payMode" value="">
                    <label for="phone">Phone number</label>
                    <input type="tel" name="phone" id="phone" value="{{ $link->phone }}" autocomplete="off" placeholder="6xxxxxxxx">
                    <p class="holder" id="holder">{{ $link->person_name }}</p>
                    <div class="grid">
                        <button class="choice on" type="button" data-method="momo"><strong>MoMo / OM</strong><small>Wait for the phone</small></button>
                        <button class="choice" type="button" data-method="visa"><strong>VISA</strong><small>Card link</small></button>
                        <button class="choice on" type="button" data-fill="full"><strong>Full amount</strong><small>Pay everything due</small></button>
                        <button class="choice" type="button" data-fill="part"><strong>Partial</strong><small>Pay part now</small></button>
                        <button class="choice wide" type="button" data-fill="split"><strong>Split</strong><small>Several numbers pay this bill</small></button>
                    </div>
                    <div id="split" hidden>
                        <table>
                            <thead><tr><th>Number</th><th>Amount (XAF)</th><th></th></tr></thead>
                            <tbody id="splitBody"></tbody>
                        </table>
                        <button class="plus" type="button" id="splitAdd">+</button>
                    </div>
                    <div id="single">
                        <label for="amount">Amount (XAF)</label>
                        <input type="number" name="amount" id="amount" min="100" step="1" value="{{ (int) $link->amount }}">
                    </div>
                    <p class="left" id="left">Left after this payment: 0 XAF</p>
                    <button class="submit" type="submit" id="go">Submit</button>
                    <p class="hint" id="hint">This waits for the phone to approve.</p>
                </form>
                @if($link->payment_link && $link->method === 'visa')
                    <a class="visa" href="{{ $link->payment_link }}">Open the VISA page</a>
                @endif
            @endif
        </div>
    </div>
</div>
@if($link->status === 'paid')
<script>
(function () {
    setTimeout(function () {
        var wrap = document.querySelector('.wrap');
        if (wrap) wrap.remove();
    }, 1600);
})();
</script>
@elseif($link->status === 'pending')
<script>
(function () {
    var url = @json(route('document.pay.status', ['token' => $link->token]));
    var timer = setInterval(function () {
        fetch(url, {headers: {Accept: 'application/json'}}).then(function (res) { return res.json(); }).then(function (body) {
            if (body.status === 'paid') {
                clearInterval(timer);
                var wrap = document.querySelector('.wrap');
                if (!wrap) return;
                wrap.innerHTML = '<div class="card"><div class="body"><div class="alert ok">Payment received. The invoice has been sent.</div></div></div>';
                setTimeout(function () { wrap.remove(); }, 1600);
                return;
            }
            if (body.status === 'failed' || body.status === 'waiting') window.location.reload();
        }).catch(function () {});
    }, 3000);
})();
</script>
@elseif($link->status !== 'paid')
<script>
(function () {
    var due = {{ (int) $link->amount }};
    var name = @json($link->person_name);
    var method = document.getElementById('payWith');
    var mode = document.getElementById('payMode');
    var amount = document.getElementById('amount');
    var phone = document.getElementById('phone');
    var holder = document.getElementById('holder');
    var left = document.getElementById('left');
    var hint = document.getElementById('hint');
    var split = document.getElementById('split');
    var single = document.getElementById('single');
    var body = document.getElementById('splitBody');
    function operator(value) {
        var digits = String(value || '').replace(/\D/g, '').replace(/^237/, '');
        var three = digits.slice(0, 3);
        var two = digits.slice(0, 2);
        if (digits.length < 9) return '';
        if (two === '67' || (three >= '650' && three <= '654') || (three >= '680' && three <= '683')) return 'MTN';
        return 'Orange';
    }
    function money(value) {
        var number = parseInt(value, 10);
        return isNaN(number) ? 0 : number;
    }
    function splitSum() {
        var sum = 0;
        body.querySelectorAll('[name="split_amount[]"]').forEach(function (input) { sum += money(input.value); });
        return sum;
    }
    function paint() {
        var paid = mode.value === 'split' ? splitSum() : money(amount.value);
        var op = operator(phone.value);
        holder.textContent = name + (op ? ' · ' + op : '');
        left.textContent = 'Left after this payment: ' + Math.max(0, due - paid).toLocaleString() + ' XAF';
        hint.textContent = mode.value === 'split'
            ? 'Each number pays its own amount.'
            : (method.value === 'visa' ? 'A card link is sent to this number.' : 'This waits for the phone to approve.');
    }
    function row(number, value) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="tel" name="split_phone[]" placeholder="6xxxxxxxx" value="' + (number || '') + '"></td>'
            + '<td><input type="number" name="split_amount[]" min="100" step="1" value="' + (value || '') + '"></td>'
            + '<td><button type="button" class="remove">Remove</button></td>';
        body.appendChild(tr);
    }
    function setMethod(value) {
        method.value = value;
        document.querySelectorAll('[data-method]').forEach(function (button) {
            button.classList.toggle('on', button.getAttribute('data-method') === value);
        });
        if (value === 'visa' && mode.value === 'split') setFill('full');
        paint();
    }
    function setFill(value) {
        mode.value = value === 'split' ? 'split' : '';
        split.hidden = value !== 'split';
        single.hidden = value === 'split';
        amount.disabled = value === 'split';
        body.querySelectorAll('input').forEach(function (input) { input.disabled = value !== 'split'; });
        document.querySelectorAll('[data-fill]').forEach(function (button) {
            button.classList.toggle('on', button.getAttribute('data-fill') === value);
        });
        if (value === 'full') amount.value = due;
        if (value === 'part') { amount.value = ''; amount.focus(); }
        if (value === 'split' && !body.children.length) { row(phone.value, ''); row('', ''); }
        paint();
    }
    document.querySelectorAll('[data-method]').forEach(function (button) {
        button.addEventListener('click', function () { setMethod(button.getAttribute('data-method')); });
    });
    document.querySelectorAll('[data-fill]').forEach(function (button) {
        button.addEventListener('click', function () { setFill(button.getAttribute('data-fill')); });
    });
    document.getElementById('splitAdd').addEventListener('click', function () { row('', ''); });
    body.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove')) event.target.closest('tr').remove();
        paint();
    });
    body.addEventListener('input', paint);
    amount.addEventListener('input', paint);
    phone.addEventListener('input', paint);
    document.getElementById('payForm').addEventListener('submit', function (event) {
        if (mode.value !== 'split') {
            var paying = money(amount.value);
            if (paying < 100 || paying > due) {
                event.preventDefault();
                hint.textContent = 'Enter a whole amount from 100 XAF up to the amount still due.';
                return;
            }
            var go = document.getElementById('go');
            go.disabled = true;
            go.textContent = 'Sending…';
            return;
        }
        var count = 0;
        body.querySelectorAll('[name="split_amount[]"]').forEach(function (input) { if (money(input.value) > 0) count++; });
        if (count < 2 || splitSum() > due) {
            event.preventDefault();
            hint.textContent = count < 2 ? 'Add at least two numbers and amounts.' : 'The split is more than the amount still due.';
            return;
        }
        var splitGo = document.getElementById('go');
        splitGo.disabled = true;
        splitGo.textContent = 'Sending…';
    });
    paint();
})();
</script>
@endif
</body>
</html>

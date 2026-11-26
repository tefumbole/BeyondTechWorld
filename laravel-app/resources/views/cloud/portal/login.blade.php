<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in</title>
    <style>
        body { font-family: Georgia, serif; background: #f4f7fb; margin: 0; }
        main { max-width: 420px; margin: 48px auto; background: #fff; padding: 24px; border-radius: 12px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        input, select { width: 100%; padding: 10px; box-sizing: border-box; margin-top: 4px; }
        button { margin-top: 16px; background: #0b3f90; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; }
        .bad { background: #fdecec; padding: 8px 10px; }
        .hint { color: #334155; }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="72" height="82" style="width:72px;height:82px;object-fit:contain">
    <h1>Sign in</h1>
    <p class="hint">Enter the code sent to your phone on WhatsApp. That code only signs you in. It does not link WhatsApp.</p>
    @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
    <div id="phone-step">
        <label for="country-search">Country</label>
        <input id="country-search" placeholder="Search a country" autocomplete="off">
        <input type="hidden" id="dial" value="237">
        <p id="country-chosen" class="hint">Cameroon (+237)</p>
        <div id="country-list" style="max-height:160px;overflow:auto;border:1px solid #d6deea;border-radius:8px;">
            @foreach(\App\Support\CountryDialCodes::all() as $label)
                <button type="button" class="country-item" data-dial="{{ preg_replace('/\D+/', '', $label) }}" style="display:block;width:100%;text-align:left;background:#fff;border:0;border-bottom:1px solid #e8eef6;padding:8px 10px;cursor:pointer;">{{ $label }}</button>
            @endforeach
        </div>
        <label for="phone">Phone number</label>
        <input id="phone" inputmode="tel" autocomplete="tel">
        <button type="button" id="send-code">Send WhatsApp code</button>
        <p class="bad" id="send-error" hidden></p>
    </div>
    <div id="code-step" hidden>
        <label for="otp-code">Code</label>
        <input id="otp-code" inputmode="numeric" autocomplete="one-time-code">
        <button type="button" id="check-code">Sign in</button>
        <p class="bad" id="code-error" hidden></p>
    </div>
    <p><a href="{{ route('whatsapp.mai') }}">New here? Start Whats MAI</a></p>
</main>
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var search = document.getElementById('country-search');
    var items = document.querySelectorAll('.country-item');
    if (search) {
        search.oninput = function () {
            var query = search.value.toLowerCase();
            for (var i = 0; i < items.length; i++) {
                items[i].hidden = items[i].textContent.toLowerCase().indexOf(query) === -1;
            }
        };
    }
    for (var c = 0; c < items.length; c++) {
        items[c].onclick = function () {
            document.getElementById('dial').value = this.getAttribute('data-dial');
            document.getElementById('country-chosen').textContent = this.textContent;
            if (search) search.value = '';
        };
    }
    function phone() {
        var code = String(document.getElementById('dial').value || '237').replace(/\D+/g, '');
        var digits = (document.getElementById('phone').value || '').replace(/\D+/g, '');
        if (code && digits.indexOf(code) === 0 && digits.length >= code.length + 7) return '+' + digits;
        return '+' + code + digits.replace(/^0+/, '');
    }
    function post(url, body, done) {
        var data = new FormData();
        data.append('_token', token);
        Object.keys(body).forEach(function (key) { data.append(key, body[key]); });
        fetch(url, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json().then(function (json) { done(response.ok, json); }); })
            .catch(function () { done(false, { message: 'Try again.' }); });
    }
    document.getElementById('send-code').onclick = function () {
        var error = document.getElementById('send-error');
        error.hidden = true;
        post('{{ route('cloud.login.otp') }}', { phone: phone(), dial: document.getElementById('dial').value }, function (ok, json) {
            if (!ok) {
                error.hidden = false;
                error.textContent = json.message || 'The code could not be sent.';
                return;
            }
            document.getElementById('phone-step').hidden = true;
            document.getElementById('code-step').hidden = false;
        });
    };
    document.getElementById('check-code').onclick = function () {
        var error = document.getElementById('code-error');
        error.hidden = true;
        post('{{ route('cloud.login.otp.verify') }}', {
            phone: phone(),
            dial: document.getElementById('dial').value,
            code: document.getElementById('otp-code').value,
            next: 'messaging'
        }, function (ok, json) {
            if (!ok) {
                error.hidden = false;
                error.textContent = json.message || 'That code does not match.';
                return;
            }
            window.location = json.redirect || '{{ route('cloud.messaging') }}';
        });
    };
})();
</script>
</body>
</html>

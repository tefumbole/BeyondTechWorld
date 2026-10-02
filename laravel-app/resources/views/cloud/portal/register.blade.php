<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscribe</title>
    <style>
        body { font-family: Georgia, serif; background: #f4f7fb; margin: 0; color: #142033; }
        main { max-width: 720px; margin: 32px auto; background: #fff; padding: 24px; border-radius: 12px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        input, select { width: 100%; padding: 10px; box-sizing: border-box; margin-top: 4px; }
        button, .choice { margin-top: 16px; background: #0b3f90; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; cursor: pointer; }
        .choice.alt { background: #fff; color: #0b3f90; border: 1px solid #0b3f90; margin-left: 8px; }
        .bad { background: #fdecec; padding: 8px 10px; }
        .note { background: #f4f7fb; padding: 8px 10px; }
        .card { border: 1px solid #d7e0ee; border-radius: 10px; padding: 12px; margin-top: 10px; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .step { display: none; }
        .step.on { display: block; }
    </style>
</head>
<body>
<main>
    <h1>Subscribe</h1>
    <p>Build your own company</p>
    @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
    @if(empty($onboardingOpen))
        <p>Company signup is not open yet. The subscriptions page stays available.</p>
    @else
    <p>{{ $welcome }}</p>
    @if($errors->any())<p class="bad">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('cloud.register.submit') }}" id="build-company">
        @csrf
        <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
        <input type="hidden" name="account_kind" id="account-kind" value="{{ old('account_kind', 'company') }}">
        @if(!empty($validationToken))
            <input type="hidden" name="validation_token" value="{{ $validationToken }}">
        @endif

        <section class="step on" data-step="1">
            <h2>Phone number</h2>
            <p>Enter your phone number. A verification code is sent before any service is shown.</p>
            <label>Phone number</label>
            <input name="phone" id="phone" value="{{ old('phone') }}" autocomplete="tel">
            <button type="button" id="lookup-phone">Continue</button>
            <p class="bad" id="lookup-error" hidden></p>
        </section>

        <section class="step" data-step="2">
            <h2>Name</h2>
            <p class="note" id="name-note"></p>
            <label>First name</label>
            <input name="first_name" id="first-name" value="{{ old('first_name') }}">
            <label>Last name</label>
            <input name="last_name" id="last-name" value="{{ old('last_name') }}">
            <button type="button" id="send-code">Send verification code</button>
            <p class="bad" id="send-error" hidden></p>
        </section>

        <section class="step" data-step="3">
            <h2>Verification code</h2>
            <p>Enter the code sent to this phone on WhatsApp.</p>
            <label>Code</label>
            <input id="otp-code" inputmode="numeric" autocomplete="one-time-code">
            <button type="button" id="check-code">Verify and continue</button>
            <p class="bad" id="code-error" hidden></p>
        </section>

        <section class="step" data-step="4">
            <h2>Services</h2>
            <p>Select one or more. The total updates as you tick them.</p>
            @foreach($plans as $plan)
                @if(($selected ?? '') !== '' && ($selected ?? '') !== $plan->module->code)
                    @continue
                @endif
                <label class="card">
                    <input type="checkbox" name="modules[]" value="{{ $plan->module->code }}" data-price="{{ $plan->price }}" data-name="{{ $plan->name }}" {{ old('modules') ? (in_array($plan->module->code, (array) old('modules'), true) ? 'checked' : '') : (($selected ?? '') === $plan->module->code ? 'checked' : '') }}>
                    <strong>{{ $plan->name }}</strong>
                    <span>{{ number_format((float) $plan->price, 0) }} {{ $plan->currency }} per {{ strtolower($plan->billing_interval ?: 'month') }}</span>
                    <span>Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</span>
                    <div>{{ $plan->module->description }}</div>
                </label>
            @endforeach
            <div class="card" id="price-summary">
                <strong>Selected modules</strong>
                <div id="price-lines"></div>
                <div class="row"><span>Monthly total</span><span id="price-total">0</span></div>
                <div class="row"><span>Free trial</span><span>{{ $quote['trial'] }}</span></div>
                <p>This total is the monthly price from the plan list. It is a preview. BeyondTechWorld activates the subscription separately.</p>
            </div>
            <button type="button" id="to-account">Continue</button>
        </section>

        <section class="step" data-step="5">
            <h2>Personal or company</h2>
            <p>Choose who this subscription is for, then complete the account.</p>
            <button type="button" class="choice" id="pick-personal">Personal</button>
            <button type="button" class="choice alt" id="pick-company">Company</button>
            <h2>Your account</h2>
            <p id="pay-lines"></p>
            <div class="row"><span>Monthly total</span><strong id="pay-total"></strong></div>
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}">
            <label>Password</label>
            <input type="password" name="password">
            <label>Confirm password</label>
            <input type="password" name="password_confirmation">
            <div id="company-fields">
                <h2>Company</h2>
                <label>Company name</label>
                <input name="company_name" id="company-name" value="{{ old('company_name') }}">
                <label>System name</label>
                <input name="system_name" value="{{ old('system_name') }}">
                <label>Business / legal name</label>
                <input name="legal_name" value="{{ old('legal_name') }}">
                <label>Company phone</label>
                <input name="company_phone" value="{{ old('company_phone') }}">
                <label>Company email</label>
                <input type="email" name="company_email" value="{{ old('company_email') }}">
                <label>Country</label>
                <input name="country" value="{{ old('country') }}" maxlength="8">
                <label>City</label>
                <input name="city" value="{{ old('city') }}">
                <label>Address</label>
                <input name="address" value="{{ old('address') }}">
            </div>
            <label>Timezone</label>
            <select name="timezone">
                @foreach(['Africa/Douala', 'Africa/Lagos', 'UTC'] as $zone)
                    <option value="{{ $zone }}" {{ old('timezone', 'Africa/Douala') === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                @endforeach
            </select>
            <label>Currency</label>
            <select name="currency">
                @foreach(['XAF', 'USD', 'EUR', 'NGN', 'GHS'] as $code)
                    <option value="{{ $code }}" {{ old('currency', 'XAF') === $code ? 'selected' : '' }}>{{ $code }}</option>
                @endforeach
            </select>
            <button type="submit">Start free trial</button>
        </section>
    </form>
    <script>
        (function () {
            var form = document.getElementById('build-company');
            var token = form.querySelector('input[name="_token"]').value;
            var kind = document.getElementById('account-kind');
            var lines = document.getElementById('price-lines');
            var total = document.getElementById('price-total');
            var payTotal = document.getElementById('pay-total');
            function show(step) {
                var nodes = form.querySelectorAll('.step');
                for (var i = 0; i < nodes.length; i++) {
                    nodes[i].className = nodes[i].getAttribute('data-step') === String(step) ? 'step on' : 'step';
                }
            }
            function paint() {
                var sum = 0;
                var html = '';
                var boxes = form.querySelectorAll('input[name="modules[]"]');
                for (var i = 0; i < boxes.length; i++) {
                    if (!boxes[i].checked) continue;
                    var price = parseFloat(boxes[i].getAttribute('data-price') || '0');
                    sum += price;
                    html += '<div class="row"><span>' + boxes[i].getAttribute('data-name') + '</span><span>' + price + '</span></div>';
                }
                lines.innerHTML = html;
                total.textContent = sum + ' XAF';
                payTotal.textContent = sum + ' XAF';
            }
            function post(url, body, done) {
                var data = new FormData();
                data.append('_token', token);
                Object.keys(body).forEach(function (key) { data.append(key, body[key]); });
                fetch(url, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (response) { return response.json().then(function (json) { done(response.ok, json); }); })
                    .catch(function () { done(false, { message: 'Try again.' }); });
            }
            document.getElementById('pick-personal').onclick = function () {
                kind.value = 'personal';
                document.getElementById('company-fields').style.display = 'none';
            };
            document.getElementById('pick-company').onclick = function () {
                kind.value = 'company';
                document.getElementById('company-fields').style.display = '';
            };
            document.getElementById('lookup-phone').onclick = function () {
                var error = document.getElementById('lookup-error');
                error.hidden = true;
                post('{{ route('cloud.register.identity') }}', { phone: document.getElementById('phone').value, account_kind: kind.value }, function (ok, json) {
                    if (!ok) {
                        error.hidden = false;
                        error.textContent = json.message || 'Enter a valid phone number.';
                        return;
                    }
                    document.getElementById('first-name').value = json.first_name || '';
                    document.getElementById('last-name').value = json.last_name || '';
                    document.getElementById('name-note').textContent = json.source === 'whatsapp'
                        ? 'This name is the one saved on WhatsApp for this number. Change it if it is not you.'
                        : 'No WhatsApp name is saved for this number. Enter the name.';
                    show(2);
                });
            };
            document.getElementById('send-code').onclick = function () {
                var error = document.getElementById('send-error');
                error.hidden = true;
                post('{{ route('cloud.register.otp') }}', {
                    phone: document.getElementById('phone').value,
                    first_name: document.getElementById('first-name').value,
                    last_name: document.getElementById('last-name').value,
                    account_kind: kind.value
                }, function (ok, json) {
                    if (!ok) {
                        error.hidden = false;
                        error.textContent = json.message || 'The code could not be sent.';
                        return;
                    }
                    show(3);
                });
            };
            document.getElementById('check-code').onclick = function () {
                var error = document.getElementById('code-error');
                error.hidden = true;
                post('{{ route('cloud.register.otp.verify') }}', {
                    phone: document.getElementById('phone').value,
                    code: document.getElementById('otp-code').value
                }, function (ok, json) {
                    if (!ok) {
                        error.hidden = false;
                        error.textContent = json.message || 'That code does not match.';
                        return;
                    }
                    if (kind.value === 'personal') {
                        document.getElementById('company-name').value = (document.getElementById('first-name').value + ' ' + document.getElementById('last-name').value).trim();
                    }
                    show(4);
                });
            };
            document.getElementById('to-account').onclick = function () { show(5); };
            form.addEventListener('change', paint);
            form.addEventListener('submit', function () {
                if (kind.value === 'personal') {
                    document.getElementById('company-name').value = (document.getElementById('first-name').value + ' ' + document.getElementById('last-name').value).trim();
                }
            });
            paint();
        })();
    </script>
    @endif
</main>
</body>
</html>

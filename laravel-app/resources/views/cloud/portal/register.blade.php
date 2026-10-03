@extends('beyond.layout')

@section('title', 'Subscribe')
@section('meta_description', 'Start a Beyond Cloud subscription as an individual or a company.')

@push('head')
<style>
    .step { display: none; text-align: left; }
    .step.on { display: block; }
    .step[data-step="1"] { text-align: center; }
    .subscribe-hero {
        position: relative;
        min-height: calc(100vh - 7.5rem);
        background-size: cover;
        background-position: center;
    }
    .subscribe-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0, 20, 50, .45), rgba(0, 40, 85, .72));
    }
    .subscribe-panel {
        position: relative;
        background: rgba(0, 32, 72, .55);
        border: 1px solid rgba(212, 175, 55, .65);
        box-shadow: 0 18px 50px rgba(0, 0, 0, .28);
        backdrop-filter: blur(8px);
        color: #fff;
    }
    .subscribe-panel h1,
    .subscribe-panel h2,
    .subscribe-panel p,
    .subscribe-panel label,
    .subscribe-panel strong { color: #fff; }
    .subscribe-panel .hint { color: rgba(255,255,255,.88); }
    .subscribe-panel input,
    .subscribe-panel select {
        background: rgba(255,255,255,.96);
        color: #002855;
        border: 0;
        border-radius: 999px;
    }
    .subscribe-choice {
        min-height: 4.5rem;
        border-radius: 999px;
        font-weight: 800;
        font-size: 1.15rem;
    }
    .field-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-top: 0.85rem;
        text-align: left;
    }
    .field-row label {
        flex: 0 0 9.5rem;
        margin: 0;
        font-weight: 650;
        text-align: left;
    }
    .field-row input,
    .field-row select {
        flex: 1 1 auto;
        min-width: 0;
        margin: 0;
        width: auto;
    }
    .known-name {
        text-align: left;
        font-size: 1.25rem;
        font-weight: 800;
        color: #D4AF37;
        margin-top: 0.4rem;
    }
</style>
@endpush

@section('content')
<section class="subscribe-hero py-10 sm:py-16" style="background-image:url('{{ \App\Support\SiteContent::image('home.hero_image', '/branding/beyond-hero.png') }}');">
    <div class="relative z-10 max-w-3xl mx-auto px-4">
        @if(session('not_permitted'))
            <p class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3">{{ session('not_permitted') }}</p>
        @endif
        @if(empty($onboardingOpen))
            <div class="subscribe-panel rounded-3xl p-6 text-center">
                <h1 class="text-3xl font-bold">Subscribe</h1>
                <p class="mt-3">Company signup is not open yet. The subscriptions page stays available.</p>
            </div>
        @else
        <p class="text-center text-white drop-shadow mb-4">{{ $welcome }}</p>
        @if($errors->any())
            <p class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('cloud.register.submit') }}" id="build-company" class="subscribe-panel rounded-3xl p-5 sm:p-8 text-center">
            @csrf
            <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
            <input type="hidden" name="account_kind" id="account-kind" value="{{ old('account_kind', 'company') }}">
            @if(!empty($validationToken))
                <input type="hidden" name="validation_token" value="{{ $validationToken }}">
            @endif

            <section class="step on" data-step="1">
                <h1 class="text-3xl sm:text-4xl font-bold drop-shadow">Subscribe</h1>
                <p class="hint mt-2">Build your own company. Choose Individual or Company.</p>
                <div class="flex flex-col sm:flex-row items-stretch justify-center gap-3 mt-6">
                    <button type="button" id="pick-personal" class="subscribe-choice bg-brand-gold hover:bg-[#b5952f] text-brand-blue px-7 shadow-[0_0_15px_rgba(212,175,55,0.45)]">Individual</button>
                    <button type="button" id="pick-company" class="subscribe-choice bg-white/15 hover:bg-white/25 border border-brand-gold text-brand-gold px-7">Company</button>
                </div>
            </section>

            <section class="step" data-step="2">
                <h2 class="text-xl font-bold">Phone number</h2>
                <p class="hint mt-1">A verification code is sent on WhatsApp before any service is shown.</p>
                <div class="field-row">
                    <label for="phone">Phone number</label>
                    <input class="px-4 py-2" name="phone" id="phone" value="{{ old('phone') }}" autocomplete="tel">
                </div>
                <button type="button" id="send-code" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Send WhatsApp code</button>
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="send-error" hidden></p>
            </section>

            <section class="step" data-step="3">
                <h2 class="text-xl font-bold">Verification code</h2>
                <p class="hint mt-1">Enter the code sent to this phone on WhatsApp.</p>
                <div class="field-row">
                    <label for="otp-code">Code</label>
                    <input class="px-4 py-2" id="otp-code" inputmode="numeric" autocomplete="one-time-code">
                </div>
                <button type="button" id="check-code" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Verify and continue</button>
                <p class="hint mt-3" id="resend-wait">You can resend the code in 2:00</p>
                <button type="button" id="resend-code" class="mt-2 bg-white/15 border border-brand-gold text-brand-gold font-bold rounded-full px-5 py-2.5" disabled>Resend code</button>
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="code-error" hidden></p>
            </section>

            <section class="step" data-step="4">
                <h2 class="text-xl font-bold" id="name-title">Your name</h2>
                <p class="hint mt-2" id="name-note"></p>
                <div class="field-row">
                    <label for="full-name">Name</label>
                    <input class="px-4 py-2" name="full_name" id="full-name" value="{{ old('full_name', trim(old('first_name').' '.old('last_name'))) }}" autocomplete="name">
                </div>
                <input type="hidden" name="first_name" id="first-name" value="{{ old('first_name') }}">
                <input type="hidden" name="last_name" id="last-name" value="{{ old('last_name') }}">
                <button type="button" id="to-username" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Continue</button>
            </section>

            <section class="step" data-step="5">
                <h2 class="text-xl font-bold">Username</h2>
                <p class="hint mt-1">Create a username, then add the email and password.</p>
                <div class="field-row">
                    <label for="username">Username</label>
                    <input class="px-4 py-2" name="username" id="username" value="{{ old('username') }}" autocomplete="username">
                </div>
                <div class="field-row">
                    <label for="email">Email</label>
                    <input class="px-4 py-2" type="email" name="email" id="email" value="{{ old('email') }}">
                </div>
                <div class="field-row">
                    <label for="password">Password</label>
                    <input class="px-4 py-2" type="password" name="password" id="password">
                </div>
                <div class="field-row">
                    <label for="password-confirmation">Confirm password</label>
                    <input class="px-4 py-2" type="password" name="password_confirmation" id="password-confirmation">
                </div>
                <button type="button" id="after-username" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Continue</button>
            </section>

            <section class="step" data-step="6">
                <h2 class="text-xl font-bold">Company</h2>
                <p class="hint mt-1">The name, phone, and email already entered are used for this company.</p>
                <p class="known-name" id="company-known"></p>
                <input type="hidden" name="company_name" id="company-name" value="{{ old('company_name') }}">
                <input type="hidden" name="company_phone" id="company-phone" value="{{ old('company_phone') }}">
                <input type="hidden" name="company_email" id="company-email" value="{{ old('company_email') }}">
                <div class="field-row">
                    <label for="country">Country</label>
                    <input class="px-4 py-2" name="country" id="country" value="{{ old('country') }}" maxlength="8">
                </div>
                <div class="field-row">
                    <label for="city">City</label>
                    <input class="px-4 py-2" name="city" id="city" value="{{ old('city') }}">
                </div>
                <div class="field-row">
                    <label for="address">Address</label>
                    <input class="px-4 py-2" name="address" id="address" value="{{ old('address') }}">
                </div>
                <div class="field-row">
                    <label for="timezone">Timezone</label>
                    <select class="px-4 py-2" name="timezone" id="timezone">
                        @foreach(['Africa/Douala', 'Africa/Lagos', 'UTC'] as $zone)
                            <option value="{{ $zone }}" {{ old('timezone', 'Africa/Douala') === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row">
                    <label for="currency">Currency</label>
                    <select class="px-4 py-2" name="currency" id="currency">
                        @foreach(['XAF', 'USD', 'EUR', 'NGN', 'GHS'] as $code)
                            <option value="{{ $code }}" {{ old('currency', 'XAF') === $code ? 'selected' : '' }}>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" id="to-services" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Continue to services</button>
            </section>

            <section class="step" data-step="7">
                <h2 class="text-xl font-bold text-brand-blue">Services</h2>
                <p class="text-gray-600 mt-1">Select one or more. The total updates as you tick them.</p>
                @foreach($plans as $plan)
                    @if(($selected ?? '') !== '' && ($selected ?? '') !== $plan->module->code)
                        @continue
                    @endif
                    <label class="mt-3 flex gap-3 rounded-2xl border border-gray-200 p-4">
                        <input type="checkbox" name="modules[]" value="{{ $plan->module->code }}" data-price="{{ $plan->price }}" data-name="{{ $plan->name }}" {{ old('modules') ? (in_array($plan->module->code, (array) old('modules'), true) ? 'checked' : '') : (($selected ?? '') === $plan->module->code ? 'checked' : '') }}>
                        <span>
                            <strong class="text-brand-blue">{{ $plan->name }}</strong>
                            <span class="block text-sm text-gray-600">{{ number_format((float) $plan->price, 0) }} {{ $plan->currency }} per {{ strtolower($plan->billing_interval ?: 'month') }}</span>
                            <span class="block text-sm text-gray-600">Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</span>
                            <span class="block text-sm text-gray-500">{{ $plan->module->description }}</span>
                        </span>
                    </label>
                @endforeach
                <div class="mt-4 rounded-2xl bg-gray-50 border border-gray-200 p-4" id="price-summary">
                    <strong>Selected modules</strong>
                    <div id="price-lines"></div>
                    <div class="flex justify-between mt-2"><span>Monthly total</span><span id="price-total">0</span></div>
                    <div class="flex justify-between"><span>Free trial</span><span>{{ $quote['trial'] }}</span></div>
                    <p class="text-sm text-gray-600 mt-2">This total is the monthly price from the plan list. It is a preview. BeyondTechWorld activates the subscription separately.</p>
                </div>
                <button type="submit" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Start free trial</button>
            </section>
        </form>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('build-company');
        if (!form) return;
        var token = form.querySelector('input[name="_token"]').value;
        var kind = document.getElementById('account-kind');
        var lines = document.getElementById('price-lines');
        var total = document.getElementById('price-total');
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
                html += '<div class="flex justify-between"><span>' + boxes[i].getAttribute('data-name') + '</span><span>' + price + '</span></div>';
            }
            lines.innerHTML = html;
            total.textContent = sum + ' XAF';
        }
        function post(url, body, done) {
            var data = new FormData();
            data.append('_token', token);
            Object.keys(body).forEach(function (key) { data.append(key, body[key]); });
            fetch(url, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json().then(function (json) { done(response.ok, json); }); })
                .catch(function () { done(false, { message: 'Try again.' }); });
        }
        function splitName(value) {
            var name = (value || '').replace(/\s+/g, ' ').trim();
            if (!name) return ['', ''];
            var parts = name.split(' ');
            var first = parts.shift();
            var last = parts.join(' ').trim();
            return [first, last || first];
        }
        function writeName() {
            var parts = splitName(document.getElementById('full-name').value);
            document.getElementById('first-name').value = parts[0];
            document.getElementById('last-name').value = parts[1];
            return (parts[0] + ' ' + parts[1]).trim();
        }
        function personName() {
            return writeName();
        }
        var resendTimer = null;
        function startResendWait() {
            var left = 120;
            var button = document.getElementById('resend-code');
            var label = document.getElementById('resend-wait');
            if (resendTimer) clearInterval(resendTimer);
            function paintWait() {
                var minutes = Math.floor(left / 60);
                var seconds = left % 60;
                label.textContent = left > 0
                    ? 'You can resend the code in ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds
                    : 'You can resend the code.';
                button.disabled = left > 0;
            }
            paintWait();
            resendTimer = setInterval(function () {
                left -= 1;
                if (left <= 0) {
                    left = 0;
                    clearInterval(resendTimer);
                }
                paintWait();
            }, 1000);
        }
        function sendCode(errorId) {
            var error = document.getElementById(errorId);
            error.hidden = true;
            post('{{ route('cloud.register.otp') }}', {
                phone: document.getElementById('phone').value,
                account_kind: kind.value
            }, function (ok, json) {
                if (!ok) {
                    error.hidden = false;
                    error.textContent = json.message || 'The code could not be sent.';
                    return;
                }
                show(3);
                startResendWait();
            });
        }
        function applyKnownCompany() {
            var full = personName();
            document.getElementById('company-name').value = full;
            document.getElementById('company-phone').value = document.getElementById('phone').value;
            document.getElementById('company-email').value = document.getElementById('email').value;
            document.getElementById('company-known').textContent = full;
            return full;
        }
        document.getElementById('pick-personal').onclick = function () {
            kind.value = 'personal';
            document.getElementById('name-title').textContent = 'Your name';
            show(2);
        };
        document.getElementById('pick-company').onclick = function () {
            kind.value = 'company';
            document.getElementById('name-title').textContent = 'Contact person';
            show(2);
        };
        document.getElementById('send-code').onclick = function () { sendCode('send-error'); };
        document.getElementById('resend-code').onclick = function () { sendCode('code-error'); };
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
                post('{{ route('cloud.register.identity') }}', {
                    phone: document.getElementById('phone').value,
                    account_kind: kind.value
                }, function (named, identity) {
                    document.getElementById('full-name').value = named ? (identity.name || '') : '';
                    writeName();
                    var note = document.getElementById('name-note');
                    if (named && identity.source === 'campay') {
                        note.textContent = kind.value === 'company'
                            ? 'Campay returned this name for the contact person. Change it if it is not correct.'
                            : 'Campay returned this name for the phone number. Change it if it is not you.';
                    } else if (named && identity.source === 'whatsapp') {
                        note.textContent = 'This name is the one saved on WhatsApp for this number. Change it if it is not you.';
                    } else {
                        note.textContent = 'Campay and WhatsApp did not return a name. Enter it.';
                    }
                    show(4);
                });
            });
        };
        document.getElementById('to-username').onclick = function () {
            writeName();
            show(5);
        };
        document.getElementById('after-username').onclick = function () {
            applyKnownCompany();
            if (kind.value === 'personal') {
                show(7);
                return;
            }
            show(6);
        };
        document.getElementById('to-services').onclick = function () { show(7); };
        form.addEventListener('change', paint);
        form.addEventListener('submit', function () {
            applyKnownCompany();
        });
        paint();
    })();
</script>
@endpush

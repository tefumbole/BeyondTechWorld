@extends('beyond.layout')

@section('title', 'Subscribe')
@section('meta_description', 'Start a Beyond Cloud subscription as an individual or a company.')

@push('head')
<style>
    .step { display: none; }
    .step.on { display: block; }
</style>
@endpush

@section('content')
<section class="bg-gradient-to-br from-brand-blue via-[#0052A3] to-brand-blue py-8 sm:py-10">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h1 class="text-2xl sm:text-4xl font-bold text-white">Subscribe</h1>
        <p class="text-white/90 mt-2">Build your own company</p>
    </div>
</section>

<section class="py-8 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4">
        @if(session('not_permitted'))
            <p class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3">{{ session('not_permitted') }}</p>
        @endif
        @if(empty($onboardingOpen))
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-gray-700">
                <p>Company signup is not open yet. The subscriptions page stays available.</p>
            </div>
        @else
        <p class="text-center text-gray-600 mb-4">{{ $welcome }}</p>
        @if($errors->any())
            <p class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('cloud.register.submit') }}" id="build-company" class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-8">
            @csrf
            <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
            <input type="hidden" name="account_kind" id="account-kind" value="{{ old('account_kind', 'company') }}">
            @if(!empty($validationToken))
                <input type="hidden" name="validation_token" value="{{ $validationToken }}">
            @endif

            <section class="step on" data-step="1">
                <h2 class="text-xl font-bold text-brand-blue">Who is this for?</h2>
                <p class="text-gray-600 mt-1">Choose one. The next step asks for a phone number and a WhatsApp code.</p>
                <div class="grid sm:grid-cols-2 gap-4 mt-5">
                    <button type="button" id="pick-personal" class="choice text-left rounded-2xl border-2 border-brand-blue bg-brand-blue text-white p-5 hover:bg-brand-dark">
                        <span class="block text-lg font-bold">Individual</span>
                        <span class="block text-sm text-white/80 mt-1">A personal subscription in your name.</span>
                    </button>
                    <button type="button" id="pick-company" class="choice text-left rounded-2xl border-2 border-brand-gold bg-white text-brand-blue p-5 hover:bg-amber-50">
                        <span class="block text-lg font-bold">Company</span>
                        <span class="block text-sm text-gray-600 mt-1">A company, with its own details after your username.</span>
                    </button>
                </div>
            </section>

            <section class="step" data-step="2">
                <h2 class="text-xl font-bold text-brand-blue">Phone number</h2>
                <p class="text-gray-600 mt-1">Enter your phone number. A verification code is sent on WhatsApp before any service is shown.</p>
                <label class="block font-semibold mt-4 text-gray-800" for="phone">Phone number</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="phone" id="phone" value="{{ old('phone') }}" autocomplete="tel">
                <button type="button" id="send-code" class="mt-4 bg-brand-blue text-white font-semibold rounded-full px-5 py-2.5">Send WhatsApp code</button>
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="send-error" hidden></p>
            </section>

            <section class="step" data-step="3">
                <h2 class="text-xl font-bold text-brand-blue">Verification code</h2>
                <p class="text-gray-600 mt-1">Enter the code sent to this phone on WhatsApp.</p>
                <label class="block font-semibold mt-4" for="otp-code">Code</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" id="otp-code" inputmode="numeric" autocomplete="one-time-code">
                <button type="button" id="check-code" class="mt-4 bg-brand-blue text-white font-semibold rounded-full px-5 py-2.5">Verify and continue</button>
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="code-error" hidden></p>
            </section>

            <section class="step" data-step="4">
                <h2 class="text-xl font-bold text-brand-blue" id="name-title">Your name</h2>
                <p class="mt-2 rounded-xl bg-blue-50 text-brand-blue px-3 py-2" id="name-note"></p>
                <label class="block font-semibold mt-4" for="first-name">First name</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="first_name" id="first-name" value="{{ old('first_name') }}">
                <label class="block font-semibold mt-4" for="last-name">Last name</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="last_name" id="last-name" value="{{ old('last_name') }}">
                <button type="button" id="to-username" class="mt-4 bg-brand-blue text-white font-semibold rounded-full px-5 py-2.5">Continue</button>
            </section>

            <section class="step" data-step="5">
                <h2 class="text-xl font-bold text-brand-blue">Username</h2>
                <p class="text-gray-600 mt-1">Create a username for this account, then add the email and password.</p>
                <label class="block font-semibold mt-4" for="username">Username</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="username" id="username" value="{{ old('username') }}" autocomplete="username">
                <label class="block font-semibold mt-4" for="email">Email</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" type="email" name="email" id="email" value="{{ old('email') }}">
                <label class="block font-semibold mt-4" for="password">Password</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" type="password" name="password" id="password">
                <label class="block font-semibold mt-4" for="password-confirmation">Confirm password</label>
                <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" type="password" name="password_confirmation" id="password-confirmation">
                <button type="button" id="after-username" class="mt-4 bg-brand-blue text-white font-semibold rounded-full px-5 py-2.5">Continue</button>
            </section>

            <section class="step" data-step="6">
                <h2 class="text-xl font-bold text-brand-blue">Company details</h2>
                <p class="text-gray-600 mt-1">These details belong to the company. The site header and footer stay Beyond Enterprise.</p>
                <div id="company-fields">
                    <label class="block font-semibold mt-4" for="company-name">Company name</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="company_name" id="company-name" value="{{ old('company_name') }}">
                    <label class="block font-semibold mt-4" for="system-name">System name</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="system_name" id="system-name" value="{{ old('system_name') }}">
                    <label class="block font-semibold mt-4" for="legal-name">Business / legal name</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="legal_name" id="legal-name" value="{{ old('legal_name') }}">
                    <label class="block font-semibold mt-4" for="company-phone">Company phone</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="company_phone" id="company-phone" value="{{ old('company_phone') }}">
                    <label class="block font-semibold mt-4" for="company-email">Company email</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" type="email" name="company_email" id="company-email" value="{{ old('company_email') }}">
                    <label class="block font-semibold mt-4" for="country">Country</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="country" id="country" value="{{ old('country') }}" maxlength="8">
                    <label class="block font-semibold mt-4" for="city">City</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="city" id="city" value="{{ old('city') }}">
                    <label class="block font-semibold mt-4" for="address">Address</label>
                    <input class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="address" id="address" value="{{ old('address') }}">
                </div>
                <label class="block font-semibold mt-4" for="timezone">Timezone</label>
                <select class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="timezone" id="timezone">
                    @foreach(['Africa/Douala', 'Africa/Lagos', 'UTC'] as $zone)
                        <option value="{{ $zone }}" {{ old('timezone', 'Africa/Douala') === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                    @endforeach
                </select>
                <label class="block font-semibold mt-4" for="currency">Currency</label>
                <select class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2" name="currency" id="currency">
                    @foreach(['XAF', 'USD', 'EUR', 'NGN', 'GHS'] as $code)
                        <option value="{{ $code }}" {{ old('currency', 'XAF') === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
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
        function personName() {
            return (document.getElementById('first-name').value + ' ' + document.getElementById('last-name').value).trim();
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
        document.getElementById('send-code').onclick = function () {
            var error = document.getElementById('send-error');
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
                post('{{ route('cloud.register.identity') }}', {
                    phone: document.getElementById('phone').value,
                    account_kind: kind.value
                }, function (named, identity) {
                    document.getElementById('first-name').value = named ? (identity.first_name || '') : '';
                    document.getElementById('last-name').value = named ? (identity.last_name || '') : '';
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
        document.getElementById('to-username').onclick = function () { show(5); };
        document.getElementById('after-username').onclick = function () {
            if (kind.value === 'personal') {
                document.getElementById('company-name').value = personName();
                show(7);
                return;
            }
            show(6);
        };
        document.getElementById('to-services').onclick = function () { show(7); };
        form.addEventListener('change', paint);
        form.addEventListener('submit', function () {
            if (kind.value === 'personal') {
                document.getElementById('company-name').value = personName();
            }
        });
        paint();
    })();
</script>
@endpush

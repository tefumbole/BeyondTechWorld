<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Build your own company</title>
    <style>
        body { font-family: Georgia, serif; background: #f4f7fb; margin: 0; }
        main { max-width: 720px; margin: 32px auto; background: #fff; padding: 24px; border-radius: 12px; }
        label { display: block; font-weight: 700; margin-top: 12px; }
        input, select { width: 100%; padding: 10px; box-sizing: border-box; margin-top: 4px; }
        button { margin-top: 16px; background: #0b3f90; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; }
        .bad { background: #fdecec; padding: 8px 10px; }
        .card { border: 1px solid #d7e0ee; border-radius: 10px; padding: 12px; margin-top: 10px; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
    </style>
</head>
<body>
<main>
    <h1>Build your own company</h1>
    @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
    @if(empty($onboardingOpen))
        <p>Company signup is not open yet. The subscriptions page stays available.</p>
    @else
    <p>{{ $welcome }} Due today is 0. No card or mobile-money payment is collected.</p>
    @if($errors->any())<p class="bad">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ route('cloud.register.submit') }}" id="build-company">
        @csrf
        <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
        @if(!empty($validationToken))
            <input type="hidden" name="validation_token" value="{{ $validationToken }}">
        @endif
        <h2>Services</h2>
        @foreach($plans as $plan)
            <label class="card">
                <input type="checkbox" name="modules[]" value="{{ $plan->module->code }}" data-price="{{ $plan->price }}" data-name="{{ $plan->name }}" checked>
                <strong>{{ $plan->name }}</strong>
                <span>{{ number_format((float) $plan->price, 0) }} {{ $plan->currency }} per {{ strtolower($plan->billing_interval ?: 'month') }}</span>
                <span>Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</span>
                <div>{{ $plan->module->description }}</div>
            </label>
        @endforeach
        <div class="card" id="price-summary">
            <strong>Selected modules</strong>
            <div id="price-lines"></div>
            <div class="row"><span>Monthly total</span><span id="price-total"></span></div>
            <div class="row"><span>Free trial</span><span>{{ $quote['trial'] }}</span></div>
            <div class="row"><span>Due today</span><span>0 {{ $quote['currency'] }}</span></div>
            <p>The amount charged later is calculated on the server from the plan list. This summary is only a preview.</p>
        </div>
        <h2>Your account</h2>
        <label>First name</label>
        <input name="first_name" value="{{ old('first_name') }}" required>
        <label>Last name</label>
        <input name="last_name" value="{{ old('last_name') }}" required>
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>
        <label>Phone</label>
        <input name="phone" value="{{ old('phone') }}" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <label>Confirm password</label>
        <input type="password" name="password_confirmation" required>
        <h2>Company</h2>
        <label>Company name</label>
        <input name="company_name" value="{{ old('company_name') }}" required>
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
        <button type="submit">Start 24-hour free trial</button>
    </form>
    <script>
        (function () {
            var form = document.getElementById('build-company');
            var lines = document.getElementById('price-lines');
            var total = document.getElementById('price-total');
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
                total.textContent = sum;
            }
            form.addEventListener('change', paint);
            paint();
        })();
    </script>
    @endif
</main>
</body>
</html>

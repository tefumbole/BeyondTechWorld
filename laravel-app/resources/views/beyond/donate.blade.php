@extends('beyond.layout')

@section('title', 'Deposit')
@section('meta_description', 'Deposit to Beyond Enterprise with MTN, Orange, VISA, or Binance.')

@section('content')
<section class="py-6 sm:py-10" style="background:#eef3fb">
    <div class="max-w-xl mx-auto px-4">
        <div style="background:#fff;border:1px solid #d7e2f2;border-radius:22px;overflow:hidden;box-shadow:0 18px 40px rgba(7,24,56,.12)">
            <div style="background:#071833;color:#fff;padding:14px 18px;position:relative">
                <a href="{{ route('payments.hub') }}" aria-label="Back to Payments" style="position:absolute;top:12px;right:12px;width:36px;height:36px;border-radius:999px;display:grid;place-items:center;background:rgba(255,255,255,.12);color:#fff;text-decoration:none;font-size:22px;line-height:1">×</a>
                <p style="margin:0;color:#e4c36a;font-size:12px;font-weight:800;letter-spacing:.12em">DEPOSIT</p>
                <h1 style="margin:4px 48px 0 0;color:#fff;font-size:22px;line-height:1.15">Pay in</h1>
            </div>
            <div style="padding:14px;background:#f4f7fb">
            @if(session('message'))
                <div class="mb-3 rounded-lg bg-green-50 text-green-800 px-3 py-2 text-sm">{{ session('message') }}</div>
            @endif
            @if(session('not_permitted'))
                <div class="mb-3 rounded-lg bg-red-50 text-red-800 px-3 py-2 text-sm">{{ session('not_permitted') }}</div>
            @endif
            <form method="POST" action="{{ url('/donate') }}" id="donateForm">
                @csrf
                <input type="hidden" name="method" id="donateMethod" value="{{ old('method', 'momo') }}">
                <div style="background:#fff;border:1px solid #e4ebf5;border-radius:16px;padding:14px;margin-bottom:12px">
                    <div id="donateCountryRow" style="display:none;margin-bottom:10px">
                        <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2" for="donateCountry">Country</label>
                        <select id="donateCountry" name="country_code" class="w-full border border-slate-300 rounded-xl px-3 bg-white" style="min-height:48px" aria-label="Country code">
                            @foreach(\App\Support\CountryDialCodes::all() as $code => $label)
                                <option value="{{ $code }}" {{ old('country_code', '+237') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2" for="donatePhone">Phone number</label>
                    <input id="donatePhone" name="phone" type="tel" required maxlength="20" value="{{ old('phone') }}" placeholder="6xxxxxxxx" inputmode="tel" autocomplete="tel" class="w-full border border-slate-300 rounded-xl px-3" style="min-height:48px">
                    <p id="donateHint" class="text-sm font-semibold text-brand-blue m-0 mt-2" style="min-height:0"></p>
                    <div id="donateNameRow" style="{{ old('person_name') ? '' : 'display:none' }}">
                        <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2 mt-3" for="donateName">Name</label>
                        <input id="donateName" name="person_name" type="text" maxlength="191" value="{{ old('person_name') }}" class="w-full border border-slate-300 rounded-xl px-3" style="min-height:48px">
                    </div>
                    <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2 mt-3" for="donateAmount">Amount (XAF)</label>
                    <input id="donateAmount" name="amount" type="number" required min="100" max="1000000" step="1" value="{{ old('amount') }}" class="w-full border border-slate-300 rounded-xl px-3" style="min-height:48px">
                </div>
                <div style="background:#fff;border:1px solid #e4ebf5;border-radius:16px;padding:14px;margin-bottom:12px">
                    <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2" for="donateNote">Note</label>
                    <input id="donateNote" name="note" type="text" maxlength="180" value="{{ old('note') }}" class="w-full border border-slate-300 rounded-xl px-3" style="min-height:48px" placeholder="What this deposit is for">
                </div>
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <button class="donate-method rounded-2xl font-bold py-3 px-2 text-sm" type="button" data-method="momo">MoMo / OM</button>
                    <button class="donate-method rounded-2xl font-bold py-3 px-2 text-sm" type="button" data-method="visa">VISA</button>
                    <button class="donate-method rounded-2xl font-bold py-3 px-2 text-sm" type="button" data-method="crypto">Crypto</button>
                </div>
                <button class="w-full font-extrabold" type="submit" style="min-height:52px;border:0;border-radius:14px;background:#c9a24a;color:#1a1406;font-size:17px">Submit</button>
            </form>
            </div>
        </div>
    </div>
</section>
<style>
    .donate-method { background:#fff; color:#0b3f90; border:1px solid #d5deee; min-height:44px; }
    .donate-method.is-on { background:#071833; color:#fff; border-color:#071833; }
    .donate-method.is-on span, .donate-method small { display:block; }
</style>
@endsection

@push('scripts')
<script>
(function () {
    var phone = document.getElementById('donatePhone');
    var country = document.getElementById('donateCountry');
    var countryRow = document.getElementById('donateCountryRow');
    var name = document.getElementById('donateName');
    var nameRow = document.getElementById('donateNameRow');
    var hint = document.getElementById('donateHint');
    var lookupUrl = @json(route('donate.lookup'));
    var timer = null;
    function showName(on) {
        if (!nameRow) return;
        nameRow.style.display = on ? 'block' : 'none';
    }
    function lookup() {
        var code = country ? country.value : '+237';
        var digits = (phone.value || '').replace(/\D/g, '');
        if (digits.indexOf('237') === 0 && code === '+237') digits = digits.slice(3);
        var ready = code === '+237' ? digits.length === 9 : digits.length >= 6;
        if (!ready) {
            hint.textContent = '';
            return;
        }
        if (code !== '+237') {
            showName(true);
            hint.textContent = '';
            if (name) name.focus();
            return;
        }
        hint.textContent = 'Looking up this number…';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(digits) + '&country_code=' + encodeURIComponent(code), {headers: {'Accept': 'application/json'}})
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (!body.ok) {
                    showName(true);
                    hint.textContent = body.error || 'This number could not be checked.';
                    return;
                }
                if (body.name) {
                    if (name) name.value = body.name;
                    showName(false);
                    hint.textContent = body.operator ? (body.name + ' · ' + body.operator) : body.name;
                    var amount = document.getElementById('donateAmount');
                    if (amount) amount.focus();
                } else {
                    showName(true);
                    hint.textContent = 'Type the name for this number.';
                    if (name) name.focus();
                }
            })
            .catch(function () {
                showName(true);
                hint.textContent = 'This number could not be checked.';
            });
    }
    phone.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookup, 400);
    });
    if (country) country.addEventListener('change', function () {
        phone.placeholder = country.value === '+237' ? '6xxxxxxxx' : 'Phone number';
        lookup();
    });
    var methodInput = document.getElementById('donateMethod');
    var methodButtons = document.querySelectorAll('.donate-method');
    function paintMethod() {
        var current = methodInput ? methodInput.value : 'momo';
        methodButtons.forEach(function (button) {
            button.classList.toggle('is-on', button.getAttribute('data-method') === current);
        });
        if (countryRow) countryRow.style.display = current === 'momo' ? 'none' : 'block';
    }
    methodButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (methodInput) methodInput.value = button.getAttribute('data-method');
            paintMethod();
        });
    });
    paintMethod();
    if (phone && !phone.value) phone.focus();
})();
</script>
@endpush

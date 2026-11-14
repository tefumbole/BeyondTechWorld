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
                        <select id="donateCountry" class="w-full border border-slate-300 rounded-xl px-3 bg-white" style="min-height:48px" aria-label="Country code">
                            @foreach(\App\Support\CountryDialCodes::all() as $code => $label)
                                <option value="{{ $code }}" {{ old('country_code', '+237') === $code ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2" for="donatePhone">Phone number</label>
                    <div class="donate-search">
                        <input id="donatePhone" type="tel" maxlength="20" placeholder="6xxxxxxxx" inputmode="tel" autocomplete="tel" class="border border-slate-300 rounded-xl px-3" style="min-height:48px">
                        <button class="donate-plus" type="button" id="donateAdd" aria-label="Add a number">+</button>
                    </div>
                    <p id="donateHint" class="text-sm font-semibold text-brand-blue m-0 mt-2"></p>
                </div>
                <div style="background:#fff;border:1px solid #e4ebf5;border-radius:16px;padding:14px;margin-bottom:12px">
                    <h2 class="text-base font-extrabold text-brand-blue m-0">Amounts</h2>
                    <p id="donateEmpty" class="text-sm text-slate-500 m-0 mt-2">No one added yet. Enter a phone number above.</p>
                    <div id="donateBody"></div>
                </div>
                <div style="background:#fff;border:1px solid #e4ebf5;border-radius:16px;padding:14px;margin-bottom:12px">
                    <label class="block text-xs font-bold tracking-wide text-slate-500 mb-2" for="donateNote">Note</label>
                    <input id="donateNote" name="note" type="text" maxlength="180" value="{{ old('note') }}" class="w-full border border-slate-300 rounded-xl px-3" style="min-height:48px" placeholder="What this deposit is for">
                </div>
                <div class="donate-pay">
                    <button class="donate-method" type="button" data-method="momo">MoMo / OM</button>
                    <button class="donate-method" type="button" data-method="visa">VISA</button>
                    <button class="donate-method" type="button" data-method="crypto">Crypto</button>
                </div>
                <button class="w-full font-extrabold" type="submit" style="min-height:52px;border:0;border-radius:14px;background:#c9a24a;color:#1a1406;font-size:17px">Submit</button>
            </form>
            </div>
        </div>
    </div>
</section>
<style>
    .donate-search { display:flex; gap:8px; align-items:center; }
    .donate-search input { flex:1; min-width:0; width:auto; }
    .donate-plus {
        width:48px; height:48px; flex:0 0 auto; border:0; border-radius:16px;
        background:#0b3f90; color:#fff; font-size:26px; line-height:1; font-weight:700; cursor:pointer;
    }
    .donate-pay { display:flex; flex-wrap:nowrap; gap:8px; margin:0 0 12px; }
    .donate-method {
        flex:1 1 0; width:auto; min-width:0; min-height:48px;
        border-radius:14px; border:1px solid #d5deee; background:#fff; color:#0b3f90;
        font-weight:800; font-size:14px; padding:8px 4px; white-space:nowrap;
    }
    .donate-method.is-on { background:#071833; color:#fff; border-color:#071833; }
    .donate-person { padding-top:12px; margin-top:12px; border-top:1px solid #e4ebf5; }
    .donate-who { display:flex; gap:10px; align-items:flex-start; }
    .donate-who strong { display:block; color:#12203a; line-height:1.25; }
    .donate-who span { display:block; color:#66748c; font-size:13px; margin-top:2px; }
    .donate-remove {
        margin-left:auto; border:0; background:#e8f0fb; color:#0b3f90;
        border-radius:999px; padding:6px 10px; font-weight:700; font-size:13px; cursor:pointer;
    }
</style>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('donateForm');
    var phone = document.getElementById('donatePhone');
    var country = document.getElementById('donateCountry');
    var countryRow = document.getElementById('donateCountryRow');
    var hint = document.getElementById('donateHint');
    var body = document.getElementById('donateBody');
    var empty = document.getElementById('donateEmpty');
    var lookupUrl = @json(route('donate.lookup'));
    var timer = null;
    function code() { return country ? country.value : '+237'; }
    function localDigits(value) {
        var digits = (value || '').replace(/\D/g, '');
        var dial = code().replace(/\D/g, '');
        if (dial && digits.indexOf(dial) === 0 && digits.length > dial.length + 5) digits = digits.slice(dial.length);
        return digits;
    }
    function ready(digits) {
        return code() === '+237' ? digits.length === 9 : digits.length >= 6;
    }
    function listed() {
        var found = {};
        body.querySelectorAll('[data-phone]').forEach(function (row) {
            found[row.getAttribute('data-phone')] = true;
        });
        return found;
    }
    function refresh() {
        empty.hidden = body.querySelectorAll('.donate-person').length > 0;
    }
    function addRow(person) {
        if (listed()[person.phone]) {
            hint.textContent = person.phone + ' is already on the list.';
            phone.value = '';
            return;
        }
        var row = document.createElement('article');
        row.className = 'donate-person';
        row.setAttribute('data-phone', person.phone);
        var who = document.createElement('div');
        who.className = 'donate-who';
        var text = document.createElement('div');
        var title = document.createElement('strong');
        title.textContent = person.name || 'Name';
        var line = document.createElement('span');
        line.textContent = (person.operator ? person.operator + ' · ' : '') + person.phone;
        text.appendChild(title);
        text.appendChild(line);
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'donate-remove';
        remove.textContent = 'Remove';
        remove.addEventListener('click', function () { row.remove(); refresh(); });
        who.appendChild(text);
        who.appendChild(remove);
        var amountLabel = document.createElement('label');
        amountLabel.className = 'block text-xs font-bold tracking-wide text-slate-500 mb-2 mt-3';
        amountLabel.textContent = 'Amount (XAF)';
        var amount = document.createElement('input');
        amount.name = 'amount[]';
        amount.type = 'number';
        amount.min = '100';
        amount.max = '1000000';
        amount.step = '1';
        amount.required = true;
        amount.placeholder = 'Amount';
        amount.className = 'w-full border border-slate-300 rounded-xl px-3';
        amount.style.minHeight = '48px';
        var phoneField = document.createElement('input');
        phoneField.type = 'hidden';
        phoneField.name = 'phone[]';
        phoneField.value = person.local || person.phone;
        var codeField = document.createElement('input');
        codeField.type = 'hidden';
        codeField.name = 'country_code[]';
        codeField.value = person.country || code();
        var nameField = document.createElement('input');
        nameField.name = 'person_name[]';
        nameField.maxLength = 191;
        nameField.value = person.name || '';
        if (person.name) {
            nameField.type = 'hidden';
        } else {
            nameField.type = 'text';
            nameField.required = true;
            nameField.placeholder = 'Name on this number';
            nameField.className = 'w-full border border-slate-300 rounded-xl px-3 mt-2';
            nameField.style.minHeight = '48px';
            title.textContent = 'Type the name';
        }
        row.appendChild(who);
        if (!person.name) row.appendChild(nameField);
        row.appendChild(amountLabel);
        row.appendChild(amount);
        row.appendChild(phoneField);
        row.appendChild(codeField);
        if (person.name) row.appendChild(nameField);
        body.appendChild(row);
        phone.value = '';
        hint.textContent = '';
        refresh();
        if (person.name) amount.focus();
        else nameField.focus();
    }
    function lookup() {
        var digits = localDigits(phone.value);
        var dial = code();
        if (!ready(digits)) {
            hint.textContent = '';
            return;
        }
        var asked = phone.value.trim();
        if (dial !== '+237') {
            return;
        }
        hint.textContent = 'Looking up this number…';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(digits) + '&country_code=' + encodeURIComponent(dial), {headers: {'Accept': 'application/json'}})
            .then(function (res) { return res.json(); })
            .then(function (payload) {
                if (phone.value.trim() !== asked && phone.value.trim() !== '') return;
                if (!payload.ok) {
                    hint.textContent = payload.error || 'This number could not be checked.';
                    return;
                }
                addRow({
                    name: payload.name || '',
                    phone: payload.phone || digits,
                    local: digits,
                    country: dial,
                    operator: payload.operator || ''
                });
            })
            .catch(function () { hint.textContent = 'This number could not be checked.'; });
    }
    phone.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookup, 350);
    });
    phone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookup();
        }
    });
    document.getElementById('donateAdd').addEventListener('click', function () {
        var digits = localDigits(phone.value);
        if (!ready(digits)) {
            hint.textContent = 'Enter a phone number.';
            return;
        }
        if (code() !== '+237') {
            addRow({ name: '', phone: digits, local: digits, country: code(), operator: '' });
            return;
        }
        lookup();
    });
    if (country) country.addEventListener('change', function () {
        phone.placeholder = code() === '+237' ? '6xxxxxxxx' : 'Phone number';
    });
    form.addEventListener('submit', function (event) {
        if (!body.querySelector('.donate-person') || form.getAttribute('data-sent') === '1') {
            event.preventDefault();
            hint.textContent = 'Add a phone number first.';
            return;
        }
        form.setAttribute('data-sent', '1');
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
    if (phone) phone.focus();
})();
</script>
@endpush

@extends('beyond.layout')

@section('title', 'Donate')
@section('meta_description', 'Donate to Beyond Enterprise with MTN, Orange, VISA, or Binance.')

@section('content')
<section class="py-10 sm:py-14 bg-slate-50">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold text-brand-blue m-0">Donate</h1>
            <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
            <p class="text-slate-600 text-sm m-0 mb-5">Choose a country code. Cameroon is selected. A Cameroon Mobile Money number fills in the name. Any other country can still donate with VISA or Crypto.</p>
            @if(session('not_permitted'))
                <div class="mb-4 rounded-lg bg-red-50 text-red-800 px-3 py-2 text-sm">{{ session('not_permitted') }}</div>
            @endif
            <form method="POST" action="{{ url('/donate') }}" id="donateForm">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="donatePhone">Phone number</label>
                <div class="flex gap-2 mb-1">
                    <select id="donateCountry" name="country_code" class="border border-slate-300 rounded-lg px-2 py-2 bg-white max-w-[46%]" aria-label="Country code">
                        @foreach(\App\Support\CountryDialCodes::all() as $code => $label)
                            <option value="{{ $code }}" {{ old('country_code', '+237') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input id="donatePhone" name="phone" type="tel" required maxlength="20" value="{{ old('phone') }}" placeholder="6xxxxxxxx" inputmode="tel" autocomplete="tel" class="min-w-0 flex-1 border border-slate-300 rounded-lg px-3 py-2">
                </div>
                <p id="donateHint" class="text-sm text-slate-500 min-h-[1.25rem] mb-3"></p>
                <label class="block text-sm font-semibold mb-1" for="donateName">Name</label>
                <input id="donateName" name="person_name" type="text" required maxlength="191" value="{{ old('person_name') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-4">
                <label class="block text-sm font-semibold mb-1" for="donateAmount">Amount (XAF)</label>
                <input id="donateAmount" name="amount" type="number" required min="100" max="1000000" step="1" value="{{ old('amount') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-4">
                <label class="block text-sm font-semibold mb-1" for="donateNote">Note</label>
                <textarea id="donateNote" name="note" maxlength="180" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-5" placeholder="What this donation is for">{{ old('note') }}</textarea>
                <div class="flex gap-2">
                    <button class="flex-1 min-w-0 rounded-full bg-brand-blue text-white font-bold py-3 px-2 text-sm" type="submit" name="method" value="momo">Momo/OM</button>
                    <button class="flex-1 min-w-0 rounded-full border border-brand-blue text-brand-blue font-bold py-3 px-2 text-sm bg-white" type="submit" name="method" value="visa">VISA</button>
                    <button class="flex-1 min-w-0 rounded-full border border-brand-gold text-brand-blue font-bold py-3 px-2 text-sm bg-white" type="submit" name="method" value="crypto">Crypto</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var phone = document.getElementById('donatePhone');
    var country = document.getElementById('donateCountry');
    var name = document.getElementById('donateName');
    var hint = document.getElementById('donateHint');
    var lookupUrl = @json(route('donate.lookup'));
    var timer = null;
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
            hint.textContent = 'Type the name for this number.';
            return;
        }
        hint.textContent = 'Looking up this number…';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(digits) + '&country_code=' + encodeURIComponent(code), {headers: {'Accept': 'application/json'}})
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (!body.ok) {
                    hint.textContent = body.error || 'This number could not be checked.';
                    return;
                }
                if (body.name) {
                    name.value = body.name;
                    hint.textContent = body.operator ? ('Name on ' + body.operator) : 'Name found.';
                } else {
                    hint.textContent = 'No name was found. Type the name to continue.';
                }
            })
            .catch(function () { hint.textContent = 'This number could not be checked.'; });
    }
    phone.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookup, 400);
    });
    if (country) country.addEventListener('change', function () {
        phone.placeholder = country.value === '+237' ? '6xxxxxxxx' : 'Phone number';
        lookup();
    });
})();
</script>
@endpush

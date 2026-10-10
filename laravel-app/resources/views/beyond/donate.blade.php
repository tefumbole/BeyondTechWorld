@extends('beyond.layout')

@section('title', 'Donate')
@section('meta_description', 'Donate to Beyond Enterprise with MTN, Orange, or VISA.')

@section('content')
<section class="py-10 sm:py-14 bg-slate-50">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold text-brand-blue m-0">Donate</h1>
            <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
            <p class="text-slate-600 text-sm m-0 mb-5">Enter a Mobile Money number. The name on that number appears, then enter the amount and a note.</p>
            @if(session('not_permitted'))
                <div class="mb-4 rounded-lg bg-red-50 text-red-800 px-3 py-2 text-sm">{{ session('not_permitted') }}</div>
            @endif
            <form method="POST" action="{{ route('donate.store') }}" id="donateForm">
                @csrf
                <label class="block text-sm font-semibold mb-1" for="donatePhone">Phone number</label>
                <input id="donatePhone" name="phone" type="tel" required maxlength="20" value="{{ old('phone') }}" placeholder="6xxxxxxxx" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-1">
                <p id="donateHint" class="text-sm text-slate-500 min-h-[1.25rem] mb-3"></p>
                <label class="block text-sm font-semibold mb-1" for="donateName">Name</label>
                <input id="donateName" name="person_name" type="text" required maxlength="191" value="{{ old('person_name') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-4">
                <label class="block text-sm font-semibold mb-1" for="donateAmount">Amount (XAF)</label>
                <input id="donateAmount" name="amount" type="number" required min="100" max="1000000" step="1" value="{{ old('amount') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-4">
                <label class="block text-sm font-semibold mb-1" for="donateNote">Note</label>
                <textarea id="donateNote" name="note" maxlength="180" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 mb-5" placeholder="What this donation is for">{{ old('note') }}</textarea>
                <button class="w-full rounded-full bg-brand-blue text-white font-bold py-3" type="submit" name="method" value="momo">Donate with MTN or Orange</button>
                <button class="w-full rounded-full border border-brand-blue text-brand-blue font-bold py-3 mt-3 bg-white" type="submit" name="method" value="visa">Donate with VISA</button>
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var phone = document.getElementById('donatePhone');
    var name = document.getElementById('donateName');
    var hint = document.getElementById('donateHint');
    var lookupUrl = @json(route('donate.lookup'));
    var timer = null;
    function lookup() {
        var digits = (phone.value || '').replace(/\D/g, '');
        if (digits.indexOf('237') === 0) digits = digits.slice(3);
        if (digits.length !== 9) {
            hint.textContent = '';
            return;
        }
        hint.textContent = 'Looking up this number…';
        fetch(lookupUrl + '?phone=' + encodeURIComponent(digits), {headers: {'Accept': 'application/json'}})
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
})();
</script>
@endpush

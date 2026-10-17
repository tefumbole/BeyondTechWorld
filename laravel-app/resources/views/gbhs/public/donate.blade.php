@extends('gbhs.public.layout')
@section('content')
<h1>Donate</h1>
<form class="card" method="post" action="{{ route('gbhs.donate.store') }}" id="donate-form">
    @csrf
    <label for="phone">Phone number</label>
    <input id="phone" name="phone" required placeholder="6XXXXXXXX" inputmode="tel" autocomplete="tel">
    <p id="lookup-hint" class="muted"></p>
    <div id="after-phone" style="display:none">
        <label for="donor_name">Name</label>
        <input id="donor_name" name="donor_name" maxlength="160">
        <label for="amount">Amount, XAF</label>
        <input id="amount" name="amount" type="number" min="100" value="1000">
        <p class="muted">Choose how to pay.</p>
        <button class="btn gold" type="submit" name="method" value="momo">Momo/Orange</button>
        <button class="btn" type="submit" name="method" value="card">VISA</button>
        <button class="btn" type="submit" name="method" value="crypto">Bitcoin</button>
    </div>
</form>
<script>
(function () {
    var phone = document.getElementById('phone');
    var name = document.getElementById('donor_name');
    var hint = document.getElementById('lookup-hint');
    var rest = document.getElementById('after-phone');
    var timer = null;
    function lookup() {
        var digits = (phone.value || '').replace(/\D/g, '');
        if (digits.indexOf('237') === 0) digits = digits.slice(3);
        if (digits.length < 9) {
            rest.style.display = 'none';
            hint.textContent = '';
            return;
        }
        hint.textContent = 'Checking this number…';
        fetch(@json(route('gbhs.donate.lookup')) + '?phone=' + encodeURIComponent(digits), {headers: {Accept: 'application/json'}})
            .then(function (res) { return res.json(); })
            .then(function (body) {
                rest.style.display = 'block';
                if (body && body.name) {
                    name.value = body.name;
                    name.readOnly = false;
                    hint.textContent = 'Name found. You can edit it, then choose how to pay.';
                } else {
                    if (!name.value) name.value = '';
                    hint.textContent = 'No name was found. Type your name, then choose how to pay.';
                }
            })
            .catch(function () {
                rest.style.display = 'block';
                hint.textContent = 'The name could not be checked. Type your name, then choose how to pay.';
            });
    }
    phone.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(lookup, 400);
    });
})();
</script>
@endsection

@extends('gbhs.public.layout')
@section('content')
<style>
    .give { max-width: 720px; margin: 0 auto; }
</style>
<div class="give">
<h1>Donate</h1>
<form class="card" method="post" action="{{ route('gbhs.donate.store') }}" id="donate-form">
    @csrf
    <label for="phone">Phone number</label>
    <div class="phone-row">
        @include('gbhs.public.partials.country_code')
        <input id="phone" name="phone" required placeholder="675321739" inputmode="tel" autocomplete="tel">
    </div>
    <p id="lookup-hint" class="muted">Cameroon is selected. Search the country code if your number is from somewhere else.</p>
    <div id="after-phone" style="display:none">
        <label for="donor_name">Name</label>
        <input id="donor_name" name="donor_name" maxlength="160">
        <p class="muted">You can edit the name.</p>
        <label for="amount">Amount, XAF</label>
        <input id="amount" name="amount" type="number" min="100" value="1000">
        <p class="muted">Choose how to pay.</p>
        <button class="btn gold" type="submit" name="method" value="momo">Momo/Orange</button>
        <button class="btn" type="submit" name="method" value="card">VISA</button>
        <button class="btn" type="submit" name="method" value="crypto">Bitcoin</button>
    </div>
</form>
</div>
<script>
(function () {
    var phone = document.getElementById('phone');
    var name = document.getElementById('donor_name');
    var hint = document.getElementById('lookup-hint');
    var rest = document.getElementById('after-phone');
    var code = document.querySelector('#donate-form [data-cc-value]');
    gbhsLookupName(phone, code, name, hint);
    function reveal() {
        var digits = (phone.value || '').replace(/\D/g, '');
        rest.style.display = digits.length >= 8 ? 'block' : 'none';
    }
    phone.addEventListener('input', reveal);
})();
</script>
@endsection

@extends('beyond.layout')
@section('title', 'Waiting for payment')
@section('content')
<div style="max-width:32rem;margin:0 auto;padding:2rem 1rem">
    <h1>Waiting for confirmation</h1>
    <p id="hs-state">We are checking the payment with the provider. This page does not turn on access by itself.</p>
</div>
<script>
(function () {
    var url = @json($statusUrl);
    function poll() {
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (body) {
                if (body.state === 'paid' && body.login) {
                    window.location = body.login;
                    return;
                }
                if (body.state === 'failed') {
                    document.getElementById('hs-state').textContent = 'The payment was not approved. You can go back and try again.';
                    return;
                }
                setTimeout(poll, 3000);
            })
            .catch(function () { setTimeout(poll, 4000); });
    }
    poll();
})();
</script>
@endsection

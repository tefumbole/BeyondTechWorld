@extends('layout.main')
@section('content')
@include('payout.partials.style')
<section class="container-fluid pay-app">
    <div class="pay-card">
        <div class="pay-card-head"><h2>Request for Payment</h2></div>
        <div class="pay-card-body">
            <p class="pay-help">Send this link to anyone who should prepare a payment. They can choose customers, set each amount, or add a phone number. When they submit, the list appears under Payout as pending. Nothing is paid until you approve it.</p>
            <div class="pay-linkbox">
                <input type="text" id="requestUrl" class="form-control" readonly value="{{ $url }}">
                <button class="pay-go" type="button" id="copyLink">Copy link</button>
            </div>
            <p class="pay-help" style="margin-top:14px;margin-bottom:0"><a href="{{ $url }}" target="_blank" rel="noopener">Open the request form</a></p>
        </div>
    </div>
</section>
<script>
document.getElementById('copyLink').addEventListener('click', function () {
    var input = document.getElementById('requestUrl');
    var button = this;
    input.focus();
    input.select();
    var done = function () { button.textContent = 'Copied'; };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(done);
        return;
    }
    document.execCommand('copy');
    done();
});
</script>
@endsection

@extends('layout.main')
@section('content')
<section class="container-fluid">
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Request for Payment</h4></div>
        <div class="card-body">
            <p>Send this link to anyone who should prepare a payment. They can choose customers, set each amount, or add a phone number. When they submit, the list appears under Payout as pending. Nothing is paid until you approve it.</p>
            <div class="input-group" style="max-width:720px">
                <input type="text" id="requestUrl" class="form-control" readonly value="{{ $url }}">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="button" id="copyLink">Copy link</button>
                </div>
            </div>
            <p class="mt-3 mb-0"><a href="{{ $url }}" target="_blank" rel="noopener">Open the request form</a></p>
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

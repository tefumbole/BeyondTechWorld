@extends('beyond.layout')

@section('title', 'Whats MAI')
@section('meta_description', 'Add Whats MAI to your phone and sign in with your number and name.')

@push('head')
<link rel="manifest" href="{{ asset('whats-mai.webmanifest') }}">
<meta name="theme-color" content="#072f6b">
<style>
    .mai-open { max-width: 520px; margin: 0 auto; background: rgba(7, 47, 107, .92); color: #fff; border: 1px solid rgba(212,175,55,.55); border-radius: 24px; padding: 1.4rem; }
    .mai-open h1 { margin: 0.4rem 0; font-size: 1.8rem; }
    .mai-open p, .mai-open label { color: rgba(255,255,255,.9); }
    .mai-open input { width: 100%; box-sizing: border-box; border-radius: 12px; border: 0; padding: 0.7rem 0.8rem; margin-top: 0.25rem; }
    .mai-open button.gold { background: #D4AF37; color: #072f6b; border: 0; border-radius: 999px; font-weight: 700; padding: 0.7rem 1.1rem; margin-top: 0.8rem; cursor: pointer; }
    .mai-open a.gold { display: inline-block; background: #D4AF37; color: #072f6b; border-radius: 999px; font-weight: 700; padding: 0.7rem 1.1rem; margin-top: 0.8rem; text-decoration: none; }
    .country-list { max-height: 160px; overflow: auto; background: #fff; border-radius: 12px; margin-top: 0.4rem; }
    .country-item { display: block; width: 100%; text-align: left; background: #fff; color: #10233f; border: 0; border-bottom: 1px solid #e8eef6; padding: 0.45rem 0.7rem; font-weight: 600; cursor: pointer; }
    .bad { background: #fdecec; color: #7f1d1d; border-radius: 12px; padding: 0.6rem 0.8rem; }
    .ok { background: rgba(212,175,55,.2); border-radius: 12px; padding: 0.6rem 0.8rem; }
</style>
@endpush

@section('content')
<section class="py-10 sm:py-16" style="background:#041833;">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mai-open">
            <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="72" height="82" style="width:72px;height:82px;object-fit:contain">
            <h1>Whats MAI</h1>
            <p>Add this page to your phone home screen. Then sign in with your number and name. The code only signs you in. It does not link WhatsApp.</p>
            <p>On iPhone, tap Share, then Add to Home Screen.</p>
            <button type="button" id="install-app" class="gold" data-mai-install data-note="install-note">Download for Android</button>
            <p id="install-note" hidden>Open this page in Chrome, tap the menu, then Install app. On iPhone, tap Share, then Add to Home Screen.</p>
            @if(session('message'))<p class="ok">{{ session('message') }}</p>@endif
            @if(session('not_permitted'))<p class="bad">{{ session('not_permitted') }}</p>@endif
            @if($signedIn)
                <p>You are signed in.</p>
                <a class="gold" href="{{ route('whatsapp.mai.home') }}">Open Whats MAI</a>
            @else
                <form method="POST" action="{{ route('whatsapp.mai.join') }}" id="mai-join">
                    @csrf
                    <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
                    <input type="hidden" name="dial" id="dial" value="{{ old('dial', '237') }}">
                    <label for="country-search">Country</label>
                    <input id="country-search" placeholder="Search a country" autocomplete="off">
                    <p id="country-chosen">Cameroon (+237)</p>
                    <div id="country-list" class="country-list">
                        @foreach($countries as $label)
                            <button type="button" class="country-item" data-dial="{{ preg_replace('/\D+/', '', $label) }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" required>
                    <label for="full-name">Name</label>
                    <input id="full-name" name="full_name" value="{{ old('full_name') }}" autocomplete="name" required>
                    <button type="button" class="gold" id="send-code">Send WhatsApp code</button>
                    <p class="bad" id="send-error" hidden></p>
                    <label for="otp-code">Code</label>
                    <input id="otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                    <button type="submit" class="gold">Sign in to Whats MAI</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    if (!document.querySelector('script[src*="mai-install.js"]')) {
        var installScript = document.createElement('script');
        installScript.src = '/public/js/mai-install.js?v={{ \App\Support\AppVersion::erp() }}';
        document.body.appendChild(installScript);
    }
    var form = document.getElementById('mai-join');
    if (!form) return;
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';
    var search = document.getElementById('country-search');
    var items = document.querySelectorAll('.country-item');
    if (search) {
        search.oninput = function () {
            var query = search.value.toLowerCase();
            for (var i = 0; i < items.length; i++) {
                items[i].hidden = items[i].textContent.toLowerCase().indexOf(query) === -1;
            }
        };
    }
    for (var c = 0; c < items.length; c++) {
        items[c].onclick = function () {
            document.getElementById('dial').value = this.getAttribute('data-dial');
            document.getElementById('country-chosen').textContent = this.textContent;
            if (search) search.value = '';
        };
    }
    function phone() {
        var code = String(document.getElementById('dial').value || '237').replace(/\D+/g, '');
        var digits = (document.getElementById('phone').value || '').replace(/\D+/g, '');
        if (code && digits.indexOf(code) === 0 && digits.length >= code.length + 7) return '+' + digits;
        return '+' + code + digits.replace(/^0+/, '');
    }
    document.getElementById('send-code').onclick = function () {
        var error = document.getElementById('send-error');
        error.hidden = true;
        var data = new FormData();
        data.append('_token', token);
        data.append('phone', phone());
        data.append('dial', document.getElementById('dial').value);
        data.append('account_kind', 'personal');
        data.append('mai', '1');
        fetch('{{ route('cloud.register.otp') }}', { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json().then(function (json) {
                if (!response.ok) {
                    error.hidden = false;
                    error.textContent = json.message || 'The code could not be sent.';
                    return;
                }
                document.getElementById('otp-code').focus();
            }); })
            .catch(function () { error.hidden = false; error.textContent = 'Try again.'; });
    };
})();
</script>
@endpush

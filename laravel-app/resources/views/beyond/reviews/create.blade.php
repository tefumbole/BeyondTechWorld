@extends('beyond.layout')

@section('title', 'Write a review | Beyond Company Limited')
@section('meta_description', 'Review Beyond Company Limited. One score out of 10: how likely you are to recommend Beyond Enterprise.')

@section('content')
<section class="rv-page">
    <div class="max-w-xl mx-auto px-4">
        <div class="rv-intro">
            <p class="rv-kicker">Beyond Enterprise</p>
            <h1>Write a review</h1>
            <p>A star rating out of 5, then how likely you are to recommend Beyond. A selfie is optional.</p>
        </div>
        @if(session('message'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3">{{ session('message') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3">
                <ul class="mb-0 pl-4">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="rv-card">
            @if($step === 'phone')
                <h2>Your phone number</h2>
                <p class="rv-hint">We send a code to this number. You type the code on this page.</p>
                <form method="POST" action="{{ route('reviews.phone') }}" class="rv-form">
                    @csrf
                    <div class="rv-field">
                        <label for="review-phone">Phone number</label>
                        <input id="review-phone" name="phone" type="tel" required value="{{ old('phone') }}" placeholder="+237 6 75 32 17 39">
                    </div>
                    <button type="submit" class="rv-send">Send code</button>
                </form>
            @elseif($step === 'otp')
                <h2>Type the code</h2>
                <p class="rv-hint">The code was sent to {{ $phone }}. Type it here on this page.</p>
                <form method="POST" action="{{ route('reviews.otp') }}" class="rv-form">
                    @csrf
                    <div class="rv-field">
                        <label for="review-otp">Code</label>
                        <input id="review-otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" placeholder="000000" class="rv-otp">
                    </div>
                    <button type="submit" class="rv-send">Confirm code</button>
                </form>
                <form method="POST" action="{{ route('reviews.phone') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $phone }}">
                    <button type="submit" class="rv-clear">Send a new code</button>
                </form>
                @include('partials.otp_assist', ['otpInputId' => 'review-otp'])
            @else
                @if($skippedOtp)
                    <p class="text-sm text-green-700 mt-0">This link is for a client already in our records, so no code is needed.</p>
                @endif
                <form id="review-form" method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" class="rv-form">
                    @csrf
                    <div class="rv-field">
                        <label for="review-name">Name on the review</label>
                        <input id="review-name" name="reviewer_name" type="text" required maxlength="191" value="{{ old('reviewer_name', $suggestedName) }}">
                        @if($suggestedName !== '')
                            <p class="rv-hint">This name is from our records. You can change it.</p>
                        @endif
                    </div>

                    <div class="country-pick rv-field">
                        <label for="country-search">Your country</label>
                        <input id="country-search" type="search" autocomplete="off" placeholder="Search your country" value="{{ old('country') }}">
                        <input type="hidden" name="country" id="review-country" value="{{ old('country') }}">
                        <div id="country-menu" class="rv-menu" hidden></div>
                    </div>

                    @php
                        $sectorOld = (string) old('sector', '');
                        $sectorShown = ($sectorOld !== '' && isset($sectors[$sectorOld])) ? $sectors[$sectorOld] : '';
                    @endphp
                    <div class="sector-pick rv-field">
                        <label for="sector-search">Your sector</label>
                        <div class="rv-combo">
                            <input id="sector-search" type="search" autocomplete="off" placeholder="Search your sector" value="{{ $sectorShown }}">
                            <div id="sector-menu" class="rv-menu" hidden></div>
                        </div>
                        <input type="hidden" name="sector" id="review-sector" value="{{ $sectorOld }}">
                        <div id="sector-other-wrap" class="mt-3 {{ $sectorOld === 'others' ? '' : 'hidden' }}">
                            <label for="sector-other">Specify the sector</label>
                            <input id="sector-other" name="sector_other" type="text" maxlength="191" value="{{ old('sector_other') }}" placeholder="Type the sector">
                        </div>
                    </div>

                    <div class="rv-score">
                        <h2>Your rating</h2>
                        <p>Score out of 5 stars.</p>
                        <input type="hidden" name="stars" id="review-stars" value="{{ old('stars', 5) }}">
                        <div class="rv-star-row" id="star-row">
                            @for($n = 1; $n <= 5; $n++)
                                <button type="button" class="rv-star" data-star="{{ $n }}" aria-label="{{ $n }} out of 5">★</button>
                            @endfor
                        </div>
                        <p class="rv-readout"><span id="star-readout">{{ old('stars', 5) }}</span>/5</p>
                    </div>

                    <div class="rv-score">
                        <h2>How likely are you to recommend Beyond Enterprise?</h2>
                        <input type="hidden" name="recommend" id="recommend-score" value="{{ old('recommend', 9) }}">
                        <div class="rv-meter" id="recommend-scale">
                            <div class="rv-meter-nums">
                                @for($n = 1; $n <= 10; $n++)
                                    <button type="button" data-score="{{ $n }}" class="recommend-score" aria-label="{{ $n }} out of 10">{{ $n }}</button>
                                @endfor
                            </div>
                            <div class="rv-meter-track" id="recommend-track">
                                <div class="rv-meter-fill" id="recommend-fill"></div>
                                <div class="rv-meter-thumb" id="recommend-thumb"></div>
                            </div>
                        </div>
                    </div>

                    <div class="rv-field">
                        <label for="review-comment">Your review</label>
                        <textarea id="review-comment" name="comment" rows="5" required minlength="20" maxlength="800" placeholder="Tell us about your experience with Beyond Company Limited.">{{ old('comment') }}</textarea>
                    </div>

                    <div class="rv-selfie">
                        <div class="rv-selfie-ring" id="selfie-frame">
                            <img id="review-photo-preview" alt="">
                            <span id="selfie-placeholder">Optional</span>
                        </div>
                        <div>
                            <p class="rv-label">Selfie <span class="rv-optional">Optional</span></p>
                            <p class="rv-hint">Add a photo of yourself, or skip this. It is not required.</p>
                            <div class="rv-selfie-actions">
                                <button type="button" id="selfie-open" class="rv-chip">Take a selfie</button>
                                <button type="button" id="selfie-file-btn" class="rv-chip rv-chip-quiet">Choose a photo</button>
                                <button type="button" class="rv-chip rv-chip-quiet" data-qr="selfie">Continue on another device</button>
                                <button type="button" id="selfie-clear" class="rv-chip rv-chip-quiet" hidden>Remove</button>
                            </div>
                            <div class="rv-qr-slot {{ ($capture['qr'] && $capture['focus'] === 'selfie') ? 'is-open' : '' }}" data-qr-slot="selfie">
                                @if($capture['qr'])
                                    <div class="rv-qr">{!! $capture['qr'] !!}</div>
                                @endif
                            </div>
                            <input id="review-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="rv-file" data-skip-image-paste="1">
                            <input id="selfie-capture" type="file" accept="image/jpeg,image/png,image/webp" capture="user" class="rv-file" data-skip-image-paste="1">
                        </div>
                    </div>
                    <div id="selfie-modal" class="rv-modal" hidden>
                        <div class="rv-modal-card">
                            <video id="selfie-video" autoplay playsinline muted></video>
                            <div class="rv-selfie-actions">
                                <button type="button" id="selfie-snap" class="rv-chip">Capture</button>
                                <button type="button" id="selfie-close" class="rv-chip rv-chip-quiet">Close</button>
                            </div>
                            <p id="selfie-error" class="rv-hint" hidden>The camera could not open. Choose a photo instead.</p>
                        </div>
                    </div>
                    <canvas id="selfie-canvas" hidden></canvas>

                    <div class="rv-field">
                        <label>Your signature</label>
                        <div class="rv-sign">
                            <canvas id="review-signature" class="touch-none"></canvas>
                        </div>
                        <input type="hidden" name="signature" id="review-signature-input">
                        <div class="rv-selfie-actions">
                            <button type="button" id="review-signature-clear" class="rv-chip rv-chip-quiet">Clear</button>
                            <button type="button" class="rv-chip rv-chip-quiet" data-qr="sign">Continue on another device</button>
                        </div>
                        <div class="rv-qr-slot {{ ($capture['qr'] && $capture['focus'] === 'sign') ? 'is-open' : '' }}" data-qr-slot="sign">
                            @if($capture['qr'])
                                <div class="rv-qr">{!! $capture['qr'] !!}</div>
                            @endif
                        </div>
                        <img id="handoff-signature" alt="" class="rv-phone-sign {{ $capture['signature_url'] !== '' ? 'is-on' : '' }}" @if($capture['signature_url'] !== '') src="{{ $capture['signature_url'] }}" @endif>
                        <input type="hidden" id="handoff-signed" value="{{ $capture['signature_url'] !== '' ? '1' : '0' }}">
                    </div>
                    @if($capture['qr'])
                        <div id="review-poll" data-url="{{ route('reviews.capture-status') }}" hidden></div>
                    @endif

                    <button type="submit" class="rv-send">Send review</button>
                </form>
                <form id="review-handoff-form" method="POST" action="{{ route('reviews.handoff') }}" hidden>
                    @csrf
                    <input type="hidden" name="focus" id="handoff-focus" value="selfie">
                </form>
            @endif
        </div>

        <p class="text-center mt-6 mb-0"><a href="{{ route('reviews.index') }}" class="text-brand-blue font-semibold">Back to reviews</a></p>
    </div>
</section>
@endsection

@push('scripts')
<style>
    .rv-page { background: radial-gradient(1200px 480px at 50% -80px, #dbe7ff 0%, #f4f7fb 42%, #eef2f7 100%); padding: 36px 0 calc(64px + env(safe-area-inset-bottom)); overflow-x: hidden; }
    .rv-intro { text-align: center; margin-bottom: 22px; }
    .rv-kicker { margin: 0 0 6px; letter-spacing: .16em; text-transform: uppercase; font-size: 11px; font-weight: 800; color: #8a7030; }
    .rv-intro h1 { margin: 0; font-size: 34px; line-height: 1.1; color: #0b3f90; letter-spacing: -0.03em; }
    .rv-intro p { margin: 8px 0 0; color: #64748b; }
    .rv-card { background: #fff; border-radius: 28px; padding: 28px 22px; box-shadow: 0 24px 60px rgba(11, 63, 144, .08); border: 1px solid rgba(255,255,255,.8); }
    .rv-card > h2 { margin: 0 0 8px; font-size: 22px; color: #0f172a; }
    .rv-otp { text-align: center; letter-spacing: .35em; font-size: 22px; font-weight: 800; }
    .rv-form { display: flex; flex-direction: column; gap: 22px; }
    .rv-field label, .rv-label { display: block; margin: 0 0 8px; font-size: 13px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #334155; }
    .rv-field input, .rv-field textarea, .rv-card > form input[type="tel"], .rv-card > form input[type="text"] {
        width: 100%; max-width: 100%; box-sizing: border-box; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px 16px; background: #f8fafc; color: #0f172a; font-size: 16px;
    }
    .rv-field input:focus, .rv-field textarea:focus { outline: none; border-color: #0b3f90; background: #fff; box-shadow: 0 0 0 4px rgba(11, 63, 144, .12); }
    .sector-pick input[type="search"] { padding-right: 42px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%230b3f90' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; }
    .rv-hint { margin: 6px 0 0; color: #64748b; font-size: 13px; }
    .rv-optional { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: 11px; letter-spacing: .04em; }
    .rv-score h2 { margin: 0 0 4px; font-size: 20px; color: #0f172a; letter-spacing: -0.02em; }
    .rv-score p { margin: 0; color: #64748b; font-size: 14px; }
    .rv-meter { margin-top: 14px; }
    .rv-meter-nums { display: flex; }
    .recommend-score { flex: 1 1 0; min-width: 0; border: 0; background: transparent; padding: 6px 0 8px; font-weight: 800; font-size: clamp(11px, 3.5vw, 15px); color: #94a3b8; }
    .recommend-score.is-on { color: #0b3f90; }
    .rv-meter-track { position: relative; height: 10px; border-radius: 999px; background: #e2e8f0; cursor: pointer; touch-action: none; }
    .rv-meter-fill { position: absolute; left: 0; top: 0; bottom: 0; width: 0; border-radius: 999px; background: linear-gradient(90deg, #60a5fa, #0b3f90); }
    .rv-meter-thumb { position: absolute; top: 50%; left: 0; width: 22px; height: 22px; border-radius: 50%; background: #0b3f90; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(11, 63, 144, .35); transform: translate(-50%, -50%); }
    .rv-star-row { display: flex; gap: 4px; margin-top: 10px; }
    .rv-star { border: 0; background: none; font-size: clamp(28px, 9vw, 36px); line-height: 1; color: #e2e8f0; padding: 6px 2px; min-width: 36px; }
    .rv-star.is-on { color: #e47911; }
    .rv-readout { margin: 10px 0 0; font-weight: 800; color: #0b3f90; }
    .rv-selfie { display: flex; gap: 16px; align-items: center; padding: 14px; border-radius: 22px; background: linear-gradient(180deg, #f8fbff, #fff); border: 1px solid #e8eef8; }
    .rv-selfie-ring { width: 92px; height: 92px; border-radius: 50%; overflow: hidden; flex: 0 0 92px; background: #e8eef8; display: grid; place-items: center; box-shadow: inset 0 0 0 3px #fff, 0 0 0 2px #c6ab47; }
    .rv-selfie-ring img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .rv-selfie-ring img.is-on { display: block; }
    .rv-selfie-ring span { font-size: 12px; font-weight: 800; color: #64748b; letter-spacing: .06em; text-transform: uppercase; }
    .rv-selfie-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .rv-chip { border: 0; border-radius: 999px; padding: 10px 14px; min-height: 44px; font-weight: 800; background: #0b3f90; color: #fff; }
    .rv-chip-quiet { background: #fff; color: #0b3f90; box-shadow: inset 0 0 0 1px #d6e0ee; }
    .rv-file { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    .rv-modal { position: fixed; inset: 0; z-index: 80; background: rgba(15, 23, 42, .62); display: grid; place-items: center; padding: 16px; }
    .rv-modal[hidden] { display: none; }
    .rv-modal-card { width: min(420px, 100%); background: #0f172a; border-radius: 24px; padding: 14px; }
    .rv-modal-card video { width: 100%; border-radius: 18px; background: #000; aspect-ratio: 3/4; object-fit: cover; transform: scaleX(-1); }
    .rv-sign { border-radius: 18px; background: #f8fafc; padding: 8px; box-shadow: inset 0 0 0 1px #e2e8f0; }
    .rv-sign canvas { width: 100%; height: 160px; background: #fff; border-radius: 12px; touch-action: none; }
    .rv-clear { margin-top: 8px; background: none; border: 0; color: #64748b; text-decoration: underline; font-size: 13px; }
    .rv-qr-slot { display: none; }
    .rv-qr-slot.is-open { display: block; }
    .rv-qr { margin-top: 10px; background: #fff; border-radius: 16px; padding: 8px; width: fit-content; box-shadow: inset 0 0 0 1px #e2e8f0; }
    .rv-qr svg { display: block; width: 168px; height: 168px; }
    .rv-phone-sign { display: none; height: 56px; margin-top: 8px; background: #fff; border-radius: 12px; }
    .rv-phone-sign.is-on { display: block; }
    .rv-send { width: 100%; border: 0; border-radius: 16px; padding: 16px; font-weight: 800; font-size: 16px; background: linear-gradient(180deg, #e4cc74, #c6ab47); color: #072f6b; box-shadow: 0 12px 24px rgba(198, 171, 71, .35); }
    .country-pick, .rv-combo { position: relative; }
    .rv-menu { position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 40; max-height: min(280px, 45vh); overflow: auto; background: #fff; border: 1px solid #d6e0ee; border-radius: 16px; box-shadow: 0 16px 40px rgba(15, 23, 42, .12); }
    .rv-menu[hidden] { display: none; }
    .rv-menu button { display: flex; width: 100%; text-align: left; gap: 8px; padding: 14px 16px; min-height: 48px; border: 0; background: #fff; font-weight: 700; color: #0f172a; font-size: 15px; }
    .rv-menu button:hover, .rv-menu button.is-on { background: #eff6ff; color: #0b3f90; }
    @media (max-width: 640px) {
        .rv-page { padding-top: 20px; }
        .rv-card { padding: 20px 14px; border-radius: 22px; }
        .rv-intro h1 { font-size: 28px; }
        .rv-score h2 { font-size: 18px; }
        .rv-selfie { flex-direction: column; align-items: stretch; text-align: left; }
        .rv-selfie-ring { margin: 0 auto; }
        .rv-selfie-actions { display: grid; grid-template-columns: 1fr; }
        .rv-chip { width: 100%; }
        .rv-qr, .rv-qr svg { max-width: 100%; }
    }
    @media (max-width: 360px) {
        .rv-card { padding: 16px 12px; }
        .rv-intro h1 { font-size: 26px; }
    }
</style>
@if($step === 'review')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var countries = @json($countries);
    var search = document.getElementById('country-search');
    var hidden = document.getElementById('review-country');
    var menu = document.getElementById('country-menu');
    function renderCountries(query) {
        var q = (query || '').toLowerCase();
        menu.innerHTML = '';
        Object.keys(countries).forEach(function (name) {
            if (q && name.toLowerCase().indexOf(q) === -1) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = countries[name] + ' ' + name;
            if (hidden.value === name) btn.className = 'is-on';
            btn.addEventListener('click', function () {
                hidden.value = name;
                search.value = name;
                menu.hidden = true;
            });
            menu.appendChild(btn);
        });
        menu.hidden = menu.children.length === 0;
    }
    if (search && menu) {
        search.addEventListener('focus', function () { renderCountries(search.value); });
        search.addEventListener('input', function () {
            hidden.value = '';
            renderCountries(search.value);
        });
        document.addEventListener('click', function (event) {
            if (!search.parentElement.contains(event.target)) menu.hidden = true;
        });
    }

    var sectors = @json($sectors);
    var sectorSearch = document.getElementById('sector-search');
    var sector = document.getElementById('review-sector');
    var sectorMenu = document.getElementById('sector-menu');
    var sectorOther = document.getElementById('sector-other-wrap');
    function toggleSector() {
        if (sectorOther) sectorOther.classList.toggle('hidden', !sector || sector.value !== 'others');
    }
    function renderSectors(query) {
        if (!sectorMenu) return;
        var q = (query || '').toLowerCase();
        sectorMenu.innerHTML = '';
        Object.keys(sectors).forEach(function (key) {
            var label = sectors[key];
            if (q && label.toLowerCase().indexOf(q) === -1) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = label;
            if (sector.value === key) btn.className = 'is-on';
            btn.addEventListener('click', function () {
                sector.value = key;
                sectorSearch.value = label;
                sectorMenu.hidden = true;
                toggleSector();
                if (key === 'others') {
                    var other = document.getElementById('sector-other');
                    if (other) other.focus();
                }
            });
            sectorMenu.appendChild(btn);
        });
        sectorMenu.hidden = sectorMenu.children.length === 0;
    }
    if (sectorSearch && sectorMenu) {
        sectorSearch.addEventListener('focus', function () {
            var exact = sector.value && sectors[sector.value] && sectorSearch.value === sectors[sector.value];
            renderSectors(exact ? '' : sectorSearch.value);
        });
        sectorSearch.addEventListener('input', function () {
            sector.value = '';
            toggleSector();
            renderSectors(sectorSearch.value);
        });
        sectorSearch.addEventListener('blur', function () {
            setTimeout(function () {
                var typed = sectorSearch.value.trim().toLowerCase();
                var match = '';
                Object.keys(sectors).forEach(function (key) {
                    if (sectors[key].toLowerCase() === typed) match = key;
                });
                if (match) {
                    sector.value = match;
                    sectorSearch.value = sectors[match];
                    toggleSector();
                }
                sectorMenu.hidden = true;
            }, 160);
        });
        document.addEventListener('click', function (event) {
            if (!sectorSearch.parentElement.contains(event.target)) sectorMenu.hidden = true;
        });
    }

    var scoreInput = document.getElementById('recommend-score');
    var track = document.getElementById('recommend-track');
    var fill = document.getElementById('recommend-fill');
    var thumb = document.getElementById('recommend-thumb');
    function paintScore(value) {
        value = Math.max(1, Math.min(10, parseInt(value, 10) || 9));
        document.querySelectorAll('.recommend-score').forEach(function (btn) {
            btn.classList.toggle('is-on', parseInt(btn.getAttribute('data-score'), 10) === value);
        });
        var pos = ((value - 0.5) / 10) * 100;
        if (fill) fill.style.width = pos + '%';
        if (thumb) thumb.style.left = pos + '%';
    }
    function scoreFromPointer(event) {
        var rect = track.getBoundingClientRect();
        var ratio = (event.clientX - rect.left) / rect.width;
        var next = Math.round(Math.max(0, Math.min(1, ratio)) * 10);
        if (next < 1) next = 1;
        if (next > 10) next = 10;
        return next;
    }
    document.querySelectorAll('.recommend-score').forEach(function (btn) {
        btn.addEventListener('click', function () {
            scoreInput.value = btn.getAttribute('data-score');
            paintScore(scoreInput.value);
        });
    });
    if (track) {
        track.addEventListener('pointerdown', function (event) {
            function move(e) {
                scoreInput.value = String(scoreFromPointer(e));
                paintScore(scoreInput.value);
            }
            move(event);
            function up() {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
            }
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        });
    }
    paintScore(scoreInput.value || '9');

    var starInput = document.getElementById('review-stars');
    var starReadout = document.getElementById('star-readout');
    function paintStars(value) {
        document.querySelectorAll('.rv-star').forEach(function (btn) {
            btn.classList.toggle('is-on', parseInt(btn.getAttribute('data-star'), 10) <= value);
        });
        if (starReadout) starReadout.textContent = value || '5';
    }
    document.querySelectorAll('.rv-star').forEach(function (btn) {
        btn.addEventListener('click', function () {
            starInput.value = btn.getAttribute('data-star');
            paintStars(parseInt(starInput.value, 10));
        });
    });
    if (starInput) paintStars(parseInt(starInput.value || '5', 10));

    var photo = document.getElementById('review-photo');
    var preview = document.getElementById('review-photo-preview');
    var placeholder = document.getElementById('selfie-placeholder');
    var photoUrl = @json($capture['photo_url'] ?? '');
    if (photoUrl && preview) {
        preview.src = photoUrl;
        preview.classList.add('is-on');
        if (placeholder) placeholder.hidden = true;
    }
    var clearPhoto = document.getElementById('selfie-clear');
    var modal = document.getElementById('selfie-modal');
    var video = document.getElementById('selfie-video');
    var snap = document.getElementById('selfie-canvas');
    var camera = null;
    function showPreview(file) {
        if (!preview) return;
        if (!file) {
            preview.classList.remove('is-on');
            preview.removeAttribute('src');
            if (placeholder) placeholder.hidden = false;
            if (clearPhoto) clearPhoto.hidden = true;
            return;
        }
        preview.src = URL.createObjectURL(file);
        preview.classList.add('is-on');
        if (placeholder) placeholder.hidden = true;
        if (clearPhoto) clearPhoto.hidden = false;
    }
    function setPhotoFile(file) {
        if (!photo || typeof DataTransfer === 'undefined') return;
        var transfer = new DataTransfer();
        transfer.items.add(file);
        photo.files = transfer.files;
        showPreview(file);
    }
    function stopCamera() {
        if (camera) {
            camera.getTracks().forEach(function (track) { track.stop(); });
            camera = null;
        }
        if (video) video.srcObject = null;
        if (modal) modal.hidden = true;
    }
    if (photo) {
        photo.addEventListener('change', function () {
            showPreview(photo.files && photo.files[0]);
        });
    }
    var captureInput = document.getElementById('selfie-capture');
    if (captureInput) {
        captureInput.addEventListener('change', function () {
            var file = captureInput.files && captureInput.files[0];
            if (file) setPhotoFile(file);
        });
    }
    var fileBtn = document.getElementById('selfie-file-btn');
    if (fileBtn && photo) fileBtn.addEventListener('click', function () { photo.click(); });
    if (clearPhoto && photo) {
        clearPhoto.addEventListener('click', function () {
            photo.value = '';
            showPreview(null);
        });
    }
    var openBtn = document.getElementById('selfie-open');
    if (openBtn && modal && video) {
        openBtn.addEventListener('click', function () {
            var err = document.getElementById('selfie-error');
            if (err) err.hidden = true;
            var fallback = document.getElementById('selfie-capture');
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (fallback) fallback.click();
                return;
            }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }).then(function (stream) {
                camera = stream;
                video.srcObject = stream;
                modal.hidden = false;
            }).catch(function () {
                if (fallback) fallback.click();
            });
        });
    }
    var closeBtn = document.getElementById('selfie-close');
    if (closeBtn) closeBtn.addEventListener('click', stopCamera);
    var snapBtn = document.getElementById('selfie-snap');
    if (snapBtn && snap && video) {
        snapBtn.addEventListener('click', function () {
            if (!video.videoWidth) return;
            snap.width = video.videoWidth;
            snap.height = video.videoHeight;
            var ctx = snap.getContext('2d');
            ctx.save();
            ctx.translate(snap.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0);
            ctx.restore();
            snap.toBlob(function (blob) {
                if (!blob) return;
                setPhotoFile(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
                stopCamera();
            }, 'image/jpeg', 0.92);
        });
    }

    var canvas = document.getElementById('review-signature');
    if (!canvas || typeof SignaturePad === 'undefined') return;
    var wrap = canvas.parentElement;
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = Math.max(wrap.clientWidth - 16, 120) * ratio;
    canvas.height = 160 * ratio;
    canvas.style.width = '100%';
    canvas.style.height = '160px';
    canvas.getContext('2d').scale(ratio, ratio);
    var pad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)', penColor: 'rgb(0, 61, 130)' });
    var clearBtn = document.getElementById('review-signature-clear');
    if (clearBtn) clearBtn.addEventListener('click', function () { pad.clear(); });
    document.getElementById('review-form').addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            alert('Choose your country from the list.');
            search.focus();
            return;
        }
        if (!sector || !sector.value) {
            e.preventDefault();
            alert('Choose a sector.');
            return;
        }
        if (!scoreInput.value) {
            e.preventDefault();
            alert('Choose how likely you are to recommend Beyond.');
            return;
        }
        if (!starInput || !starInput.value) {
            e.preventDefault();
            alert('Choose a star rating.');
            return;
        }
        var signedOnPhone = document.getElementById('handoff-signed');
        if (pad.isEmpty() && (!signedOnPhone || signedOnPhone.value !== '1')) {
            e.preventDefault();
            alert('Please sign before sending your review.');
            return;
        }
        if (!pad.isEmpty()) {
            document.getElementById('review-signature-input').value = pad.toDataURL('image/png');
        }
    });
    function startPoll(poll) {
        if (!poll || poll.getAttribute('data-polling') === '1') return;
        poll.setAttribute('data-polling', '1');
        setInterval(function () {
            fetch(poll.getAttribute('data-url'), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.photo_url && preview) {
                        preview.src = data.photo_url;
                        preview.classList.add('is-on');
                        if (placeholder) placeholder.hidden = true;
                    }
                    if (data.signature_url) {
                        var signed = document.getElementById('handoff-signature');
                        var flag = document.getElementById('handoff-signed');
                        if (signed) {
                            signed.src = data.signature_url;
                            signed.classList.add('is-on');
                        }
                        if (flag) flag.value = '1';
                    }
                });
        }, 4000);
    }
    function showQr(name) {
        document.querySelectorAll('.rv-qr-slot').forEach(function (el) { el.classList.remove('is-open'); });
        var slot = document.querySelector('[data-qr-slot="' + name + '"]');
        if (slot) slot.classList.add('is-open');
    }
    document.querySelectorAll('[data-qr]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var name = btn.getAttribute('data-qr');
            var slot = document.querySelector('[data-qr-slot="' + name + '"]');
            if (slot && slot.querySelector('svg')) {
                showQr(name);
                return;
            }
            var form = document.getElementById('review-handoff-form');
            var tokenInput = form.querySelector('input[name="_token"]');
            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: '_token=' + encodeURIComponent(tokenInput.value) + '&focus=' + encodeURIComponent(name)
            }).then(function (response) { return response.json(); }).then(function (data) {
                if (!data.qr) return;
                document.querySelectorAll('[data-qr-slot]').forEach(function (el) {
                    el.innerHTML = '<div class="rv-qr">' + data.qr + '</div>';
                });
                showQr(name);
                var poll = document.getElementById('review-poll');
                if (!poll) {
                    poll = document.createElement('div');
                    poll.id = 'review-poll';
                    document.getElementById('review-form').appendChild(poll);
                }
                poll.setAttribute('data-url', data.status_url);
                startPoll(poll);
            });
        });
    });
    startPoll(document.getElementById('review-poll'));
})();
</script>
@endif
@endpush

@extends('beyond.layout')

@section('title', 'Write a review | Beyond Company Limited')
@section('meta_description', 'Review Beyond Company Limited. One score out of 10: how likely you are to recommend Beyond Enterprise.')

@section('content')
<section class="bg-gradient-to-r from-brand-blue via-brand-light to-brand-blue py-8">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <p class="text-brand-gold font-semibold text-sm mb-2">Beyond Company Limited</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-white m-0">Write a review</h1>
        <p class="text-white/90 mt-2 mb-0">Confirm your phone number, then tell us how we did.</p>
    </div>
</section>

<section class="py-8 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4">
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

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-8">
            @if($step === 'phone')
                <h2 class="text-lg font-bold text-gray-900 mt-0">Your phone number</h2>
                <p class="text-gray-600">We will send a code to this number. You type the code on this page.</p>
                <form method="POST" action="{{ route('reviews.phone') }}" class="space-y-4">
                    @csrf
                    <label for="review-phone" class="text-sm font-semibold text-gray-700 block">Phone number</label>
                    <input id="review-phone" name="phone" type="tel" required value="{{ old('phone') }}" placeholder="+237 6 75 32 17 39" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <button type="submit" class="w-full bg-brand-gold text-brand-blue font-bold py-3.5 rounded-lg">Send code</button>
                </form>
            @elseif($step === 'otp')
                <h2 class="text-lg font-bold text-gray-900 mt-0">Type the code</h2>
                <p class="text-gray-600">The code was sent to {{ $phone }}. Type it here on this page.</p>
                <form method="POST" action="{{ route('reviews.otp') }}" class="space-y-4">
                    @csrf
                    <label for="review-otp" class="text-sm font-semibold text-gray-700 block">Code</label>
                    <input id="review-otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" required maxlength="6" placeholder="000000" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-center tracking-[0.3em]">
                    <button type="submit" class="w-full bg-brand-gold text-brand-blue font-bold py-3.5 rounded-lg">Confirm code</button>
                </form>
                <form method="POST" action="{{ route('reviews.phone') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $phone }}">
                    <button type="submit" class="text-sm text-brand-blue underline">Send a new code</button>
                </form>
                @include('partials.otp_assist', ['otpInputId' => 'review-otp'])
            @else
                @if($skippedOtp)
                    <p class="text-sm text-green-700 mt-0">This link is for a client already in our records, so no code is needed.</p>
                @endif
                <form id="review-form" method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <div>
                        <label for="review-name" class="text-sm font-semibold text-gray-700 block mb-2">Name on the review</label>
                        <input id="review-name" name="reviewer_name" type="text" required maxlength="191" value="{{ old('reviewer_name', $suggestedName) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                        @if($suggestedName !== '')
                            <p class="text-xs text-gray-500 mt-1 mb-0">This name is from our records. You can change it.</p>
                        @endif
                    </div>

                    <div class="country-pick">
                        <label for="country-search" class="text-sm font-semibold text-gray-700 block mb-2">Your country</label>
                        <input id="country-search" type="search" autocomplete="off" placeholder="Search your country" value="{{ old('country') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                        <input type="hidden" name="country" id="review-country" value="{{ old('country') }}">
                        <div id="country-menu" class="country-menu" hidden></div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-800 mb-2">Your sector</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($sectors as $key => $label)
                                <label class="sector-choice">
                                    <input type="radio" name="sector" value="{{ $key }}" {{ old('sector') === $key ? 'checked' : '' }}>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div id="sector-other-wrap" class="mt-3 {{ old('sector') === 'others' ? '' : 'hidden' }}">
                            <label for="sector-other" class="text-sm font-semibold text-gray-700 block mb-2">Specify the sector</label>
                            <input id="sector-other" name="sector_other" type="text" maxlength="191" value="{{ old('sector_other') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5" placeholder="Type the sector">
                        </div>
                    </div>

                    <div>
                        <h2 class="text-lg font-bold text-gray-900 mt-0 mb-1">How likely are you to recommend Beyond Enterprise?</h2>
                        <p class="text-sm text-gray-600 mt-0">Score out of 10. A review is shown on the website from 6 upward.</p>
                        <input type="hidden" name="recommend" id="recommend-score" value="{{ old('recommend', 9) }}">
                        <div class="flex flex-wrap gap-2 mt-3" id="recommend-scale">
                            @for($n = 1; $n <= 10; $n++)
                                <button type="button" data-score="{{ $n }}" class="recommend-score" aria-label="{{ $n }} out of 10">{{ $n }}</button>
                            @endfor
                        </div>
                        <p class="text-sm font-semibold text-brand-blue mt-2 mb-0"><span id="recommend-readout">{{ old('recommend', 9) }}</span>/10</p>
                    </div>

                    <div>
                        <label for="review-comment" class="text-sm font-semibold text-gray-700 block mb-2">Your review</label>
                        <textarea id="review-comment" name="comment" rows="5" required minlength="20" maxlength="800" class="w-full border border-gray-300 rounded-lg px-3 py-2.5" placeholder="Tell us about your experience with Beyond Company Limited.">{{ old('comment') }}</textarea>
                    </div>

                    <div>
                        <label for="review-photo" class="text-sm font-semibold text-gray-700 block mb-2">Your picture</label>
                        <input id="review-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                        <img id="review-photo-preview" alt="" class="mt-3 w-20 h-20 rounded-full object-cover hidden border-2 border-brand-gold">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-700 block mb-2">Your signature</label>
                        <div class="border-2 border-dashed border-brand-blue rounded-xl bg-blue-50 p-3">
                            <canvas id="review-signature" class="w-full bg-white rounded-lg touch-none"></canvas>
                        </div>
                        <input type="hidden" name="signature" id="review-signature-input">
                        <button type="button" id="review-signature-clear" class="mt-2 text-sm text-gray-600 underline">Clear signature</button>
                    </div>

                    <button type="submit" class="w-full bg-brand-gold text-brand-blue font-bold py-3.5 rounded-lg">Send review</button>
                </form>
            @endif
        </div>

        <p class="text-center mt-6 mb-0"><a href="{{ route('reviews.index') }}" class="text-brand-blue font-semibold">Back to reviews</a></p>
    </div>
</section>
@endsection

@push('scripts')
@if($step === 'review')
<style>
    .country-pick { position: relative; }
    .country-menu {
        position: absolute; left: 0; right: 0; top: 100%; z-index: 30; max-height: 280px; overflow: auto;
        background: #fff; border: 1px solid #d6e0ee; border-radius: 12px; box-shadow: 0 16px 40px rgba(15, 23, 42, .12); margin-top: 4px;
    }
    .country-menu button {
        display: flex; width: 100%; text-align: left; gap: 8px; padding: 10px 12px; border: 0; background: #fff; font-weight: 600; color: #0f172a;
    }
    .country-menu button:hover, .country-menu button.is-on { background: #eff6ff; }
    .sector-choice { display: flex; align-items: center; gap: 8px; border: 1px solid #d6e0ee; border-radius: 12px; padding: 10px 12px; font-weight: 800; color: #0b3f90; cursor: pointer; }
    .sector-choice input { accent-color: #0b3f90; }
    .recommend-score {
        width: 42px; height: 42px; border-radius: 12px; border: 1px solid #d6e0ee; background: #fff; font-weight: 800; color: #0b3f90;
    }
    .recommend-score.is-on { background: #0b3f90; color: #fff; border-color: #0b3f90; }
</style>
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

    document.querySelectorAll('input[name="sector"]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.getElementById('sector-other-wrap').classList.toggle('hidden', input.value !== 'others' || !input.checked);
        });
    });

    var scoreInput = document.getElementById('recommend-score');
    var readout = document.getElementById('recommend-readout');
    function paintScore(value) {
        document.querySelectorAll('.recommend-score').forEach(function (btn) {
            btn.classList.toggle('is-on', parseInt(btn.getAttribute('data-score'), 10) === value);
        });
        if (readout) readout.textContent = value || '9';
    }
    document.querySelectorAll('.recommend-score').forEach(function (btn) {
        btn.addEventListener('click', function () {
            scoreInput.value = btn.getAttribute('data-score');
            paintScore(parseInt(scoreInput.value, 10));
        });
    });
    paintScore(parseInt(scoreInput.value || '9', 10));

    var photo = document.getElementById('review-photo');
    var preview = document.getElementById('review-photo-preview');
    if (photo && preview) {
        photo.addEventListener('change', function () {
            var file = photo.files && photo.files[0];
            if (!file) {
                preview.classList.add('hidden');
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        });
    }

    var canvas = document.getElementById('review-signature');
    if (!canvas || typeof SignaturePad === 'undefined') return;
    var wrap = canvas.parentElement;
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = Math.max(wrap.clientWidth - 24, 280) * ratio;
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
        if (!document.querySelector('input[name="sector"]:checked')) {
            e.preventDefault();
            alert('Choose a sector.');
            return;
        }
        if (!scoreInput.value) {
            e.preventDefault();
            alert('Choose a score out of 10.');
            return;
        }
        if (pad.isEmpty()) {
            e.preventDefault();
            alert('Please sign before sending your review.');
            return;
        }
        document.getElementById('review-signature-input').value = pad.toDataURL('image/png');
    });
})();
</script>
@endif
@endpush

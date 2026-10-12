@extends('beyond.layout')

@section('title', 'Write a review | Beyond Company Limited')
@section('meta_description', 'Review Beyond Company Limited with your Google account, a photo, your country, and your signature.')

@section('content')
<section class="bg-gradient-to-r from-brand-blue via-brand-light to-brand-blue py-8">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <p class="text-brand-gold font-semibold text-sm mb-2">Beyond Company Limited</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-white m-0">Write a review</h1>
        <p class="text-white/90 mt-2 mb-0">Confirm your Google account, choose your country, add a photo, and sign.</p>
    </div>
</section>

<section class="py-8 bg-gray-50">
    <div class="max-w-3xl mx-auto px-4">
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
            <h2 class="text-lg font-bold text-gray-900 mt-0">1. Confirm with Google</h2>
            @if($googleClientId === '')
                <p class="text-gray-600 mb-0">Google sign-in is not connected yet, so a review cannot be sent. An administrator can connect it from the Reviews menu.</p>
            @elseif(empty($google['email']))
                <p class="text-gray-600">Use the Google account that should stand behind this review.</p>
                <form id="review-google-form" method="POST" action="{{ route('reviews.google') }}">
                    @csrf
                    <input type="hidden" name="credential" id="review-google-credential">
                </form>
                <div id="g_id_onload"
                     data-client_id="{{ $googleClientId }}"
                     data-context="signin"
                     data-callback="onReviewGoogle"
                     data-auto_prompt="false"></div>
                <div class="g_id_signin" data-type="standard" data-theme="outline" data-size="large" data-text="continue_with" data-shape="rectangular"></div>
            @else
                <div class="flex items-center gap-3 rounded-xl bg-gray-50 border border-gray-100 p-3">
                    @if(!empty($google['picture']))
                        <img src="{{ $google['picture'] }}" alt="" class="w-12 h-12 rounded-full object-cover">
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900 m-0">{{ !empty($google['name']) ? $google['name'] : 'Google account' }}</p>
                        <p class="text-sm text-green-700 m-0">Google account confirmed</p>
                    </div>
                </div>
            @endif

            @if($googleClientId !== '' && !empty($google['email']))
                <form id="review-form" method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" class="mt-8 space-y-6">
                    @csrf
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 mt-0 mb-1">2. Your country</h2>
                        <label for="review-country" class="text-sm font-semibold text-gray-700 block mb-2">Where are you reviewing from?</label>
                        <select id="review-country" name="country" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 bg-white">
                            <option value="">Choose a country</option>
                            @foreach($countries as $name => $flag)
                                <option value="{{ $name }}" {{ old('country') === $name ? 'selected' : '' }}>{{ $flag }} {{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1 mb-0">The review shows this country, for example United States or Cameroon.</p>
                    </div>

                    <div>
                        <h2 class="text-lg font-bold text-gray-900 mt-0 mb-1">3. Your rating</h2>
                        <input type="hidden" name="rating" id="review-rating" value="{{ old('rating') }}">
                        <div class="flex gap-1" id="review-stars" role="group" aria-label="Star rating">
                            @for($star = 1; $star <= 5; $star++)
                                <button type="button" data-star="{{ $star }}" class="text-3xl leading-none text-gray-300" aria-label="{{ $star }} star{{ $star > 1 ? 's' : '' }}">★</button>
                            @endfor
                        </div>
                        <p id="review-rating-note" class="text-sm text-gray-600 mt-2 mb-0">Only a 5-star review is published on the website.</p>
                    </div>

                    <div>
                        <label for="review-comment" class="text-sm font-semibold text-gray-700 block mb-2">Your review</label>
                        <textarea id="review-comment" name="comment" rows="5" required minlength="20" maxlength="800" class="w-full border border-gray-300 rounded-lg px-3 py-2.5" placeholder="What was your experience with Beyond Company Limited?">{{ old('comment') }}</textarea>
                    </div>

                    <div>
                        <label for="review-photo" class="text-sm font-semibold text-gray-700 block mb-2">Your picture</label>
                        <input id="review-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                        <p class="text-xs text-gray-500 mt-1 mb-0">Optional. JPG, PNG, or WebP, up to 4 MB. If you skip this, your Google picture is used.</p>
                        <img id="review-photo-preview" alt="" class="mt-3 w-20 h-20 rounded-full object-cover hidden border-2 border-brand-gold">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-700 block mb-2">Your signature</label>
                        <p class="text-xs text-gray-500 mb-2">Sign with your mouse or finger.</p>
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
@if($googleClientId !== '' && empty($google['email']))
<script>
function onReviewGoogle(response) {
    var input = document.getElementById('review-google-credential');
    var form = document.getElementById('review-google-form');
    if (!input || !form || !response || !response.credential) return;
    input.value = response.credential;
    form.submit();
}
</script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
@endif
@if($googleClientId !== '' && !empty($google['email']))
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var stars = document.querySelectorAll('#review-stars [data-star]');
    var ratingInput = document.getElementById('review-rating');
    var note = document.getElementById('review-rating-note');
    function paint(value) {
        stars.forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-star'), 10);
            btn.style.color = n <= value ? '#D4AF37' : '#d1d5db';
        });
        if (!note) return;
        if (!value) {
            note.textContent = 'Only a 5-star review is published on the website.';
        } else if (value === 5) {
            note.textContent = 'This review will be shown on the website.';
        } else {
            note.textContent = 'This review will be saved, and it will not appear on the website.';
        }
    }
    stars.forEach(function (btn) {
        btn.addEventListener('click', function () {
            ratingInput.value = btn.getAttribute('data-star');
            paint(parseInt(ratingInput.value, 10));
        });
    });
    paint(parseInt(ratingInput.value || '0', 10));

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
    var form = document.getElementById('review-form');
    form.addEventListener('submit', function (e) {
        if (!ratingInput.value) {
            e.preventDefault();
            alert('Please choose a star rating.');
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

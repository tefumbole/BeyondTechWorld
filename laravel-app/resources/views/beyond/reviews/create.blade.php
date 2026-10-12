@extends('beyond.layout')

@section('title', 'Write a review | Beyond Company Limited')
@section('meta_description', 'Review Beyond Company Limited. Confirm your phone, then score value, equipment, beauty, professionalism, and time.')

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
                <p class="text-gray-600">The code was sent to {{ $phone }}. The first pair is repeated, for example 47 47 82. Type it here on this page.</p>
                <form method="POST" action="{{ route('reviews.otp') }}" class="space-y-4">
                    @csrf
                    <label for="review-otp" class="text-sm font-semibold text-gray-700 block">Code</label>
                    <input id="review-otp" name="otp" type="text" inputmode="numeric" required maxlength="8" placeholder="47 47 82" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <button type="submit" class="w-full bg-brand-gold text-brand-blue font-bold py-3.5 rounded-lg">Confirm code</button>
                </form>
                <form method="POST" action="{{ route('reviews.phone') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $phone }}">
                    <button type="submit" class="text-sm text-brand-blue underline">Send a new code</button>
                </form>
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

                    <div>
                        <label for="review-country" class="text-sm font-semibold text-gray-700 block mb-2">Your country</label>
                        <select id="review-country" name="country" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 bg-white">
                            <option value="">Choose a country</option>
                            @foreach($countries as $name => $flag)
                                <option value="{{ $name }}" {{ old('country') === $name ? 'selected' : '' }}>{{ $flag }} {{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <h2 class="text-lg font-bold text-gray-900 mt-0 mb-1">Your scores</h2>
                        <p id="review-rating-note" class="text-sm text-gray-600 mt-0">The review is shown on the website only when every score is 5 stars.</p>
                        @foreach($scores as $field => $label)
                            <div class="mt-4">
                                <p class="text-sm font-semibold text-gray-800 mb-1">{{ $label }}</p>
                                <input type="hidden" name="{{ $field }}" id="score-{{ $field }}" value="{{ old($field) }}">
                                <div class="flex gap-1 review-stars" data-field="{{ $field }}">
                                    @for($star = 1; $star <= 5; $star++)
                                        <button type="button" data-star="{{ $star }}" class="text-3xl leading-none text-gray-300" aria-label="{{ $star }} stars for {{ $label }}">★</button>
                                    @endfor
                                </div>
                            </div>
                        @endforeach
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
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    document.querySelectorAll('.review-stars').forEach(function (group) {
        var field = group.getAttribute('data-field');
        var input = document.getElementById('score-' + field);
        function paint(value) {
            group.querySelectorAll('[data-star]').forEach(function (btn) {
                var n = parseInt(btn.getAttribute('data-star'), 10);
                btn.style.color = n <= value ? '#D4AF37' : '#d1d5db';
            });
        }
        group.querySelectorAll('[data-star]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = btn.getAttribute('data-star');
                paint(parseInt(input.value, 10));
            });
        });
        paint(parseInt(input.value || '0', 10));
    });

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
        var missing = false;
        document.querySelectorAll('.review-stars').forEach(function (group) {
            var input = document.getElementById('score-' + group.getAttribute('data-field'));
            if (!input.value) missing = true;
        });
        if (missing) {
            e.preventDefault();
            alert('Please choose a star score for every item.');
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

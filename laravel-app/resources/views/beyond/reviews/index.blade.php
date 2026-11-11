@extends('beyond.layout')

@section('title', 'Reviews | Beyond Company Limited')
@section('meta_description', 'Reviews from clients of Beyond Company Limited, shown with their country.')

@section('content')
<section class="bg-gradient-to-r from-brand-blue via-brand-light to-brand-blue py-8 sm:py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 text-center">
        <p class="text-brand-gold font-semibold tracking-wide text-sm mb-2">Beyond Company Limited</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-white m-0">Client reviews</h1>
        <p class="text-white/90 mt-3 mb-0 max-w-2xl mx-auto">Clients say how likely they are to recommend Beyond Enterprise, out of 10. A review appears here from 6 upward.</p>
        <a href="{{ route('reviews.create') }}" class="inline-flex items-center gap-2 mt-5 bg-brand-gold text-brand-blue font-bold px-5 py-2.5 rounded-lg">
            <i data-lucide="star" class="w-4 h-4"></i> Write a review
        </a>
    </div>
</section>

<section class="py-8 sm:py-10 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('message'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3">{{ session('message') }}</div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 m-0">Customer reviews</h2>
            <div class="flex flex-wrap items-center gap-3 mt-3">
                <span class="rv-stars" aria-hidden="true"><span style="width: {{ $summary['count'] ? min(100, $summary['average'] / 10 * 100) : 0 }}%">★★★★★</span>★★★★★</span>
                <button type="button" id="review-total" class="text-lg font-bold text-brand-blue underline" aria-expanded="false" aria-controls="review-breakdown">
                    {{ number_format($summary['average'], 1) }} <span class="font-semibold">({{ number_format($summary['count']) }})</span>
                </button>
            </div>
            <div id="review-breakdown" class="mt-4 hidden" style="max-width: 520px;">
                <p class="text-xl font-bold text-gray-900 m-0">{{ number_format($summary['average'], 1) }} out of 10</p>
                <p class="text-gray-500 mt-1 mb-4">{{ number_format($summary['count']) }} {{ $summary['count'] === 1 ? 'review' : 'reviews' }}</p>
                @foreach($summary['bars'] as $bar)
                    <div class="grid items-center gap-3 mb-2" style="grid-template-columns: 52px 1fr 48px;">
                        <span class="text-sm font-semibold text-brand-blue">{{ $bar['score'] }}/10</span>
                        <span class="rv-bar"><span style="width: {{ $bar['percent'] }}%"></span></span>
                        <span class="text-sm text-gray-600 text-right">{{ $bar['percent'] }}%</span>
                    </div>
                @endforeach
            </div>
            <button type="button" id="review-comments-toggle" class="mt-4 text-brand-blue font-semibold" aria-expanded="false" aria-controls="review-comments">
                What customers are saying
            </button>
        </div>

        <div id="review-comments" class="hidden">
        @if($reviews->isEmpty())
            <div class="text-center py-16 bg-white rounded-2xl shadow-sm">
                <i data-lucide="message-square" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                <p class="text-gray-600 m-0">Reviews from our clients will appear here.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($reviews as $review)
                    <article class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col">
                        <div class="flex items-center gap-3">
                            @if($review->portraitUrl())
                                <img src="{{ $review->portraitUrl() }}" alt="" class="w-14 h-14 rounded-full object-cover border-2 border-brand-gold">
                            @else
                                <span class="w-14 h-14 rounded-full bg-brand-blue text-white font-bold flex items-center justify-center text-lg">{{ strtoupper(substr($review->reviewer_name, 0, 1)) }}</span>
                            @endif
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-gray-900 m-0 truncate">{{ $review->reviewer_name }}</h2>
                                <p class="text-sm text-brand-blue m-0">From {{ $review->countryLabel() }}</p>
                                @if($review->sectorLabel())
                                    <p class="text-xs font-semibold text-gray-500 m-0">{{ $review->sectorLabel() }}</p>
                                @endif
                            </div>
                        </div>
                        <p class="text-sm font-bold text-gray-800 mt-3 mb-0">{{ (int) $review->rating }}/10</p>
                        <p class="text-gray-700 mt-3 mb-4 leading-relaxed flex-1">“{{ $review->comment }}”</p>
                        @if($review->signatureUrl())
                            <div class="mt-auto pt-3 border-t border-gray-100">
                                <p class="text-xs uppercase tracking-wide text-gray-400 m-0 mb-1">Signed</p>
                                <img src="{{ $review->signatureUrl() }}" alt="Signature of {{ $review->reviewer_name }}" class="h-12 max-w-full object-contain object-left">
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
        </div>
    </div>
</section>
<style>
    .rv-stars { position: relative; display: inline-block; color: #e5e7eb; font-size: 22px; letter-spacing: 1px; line-height: 1; }
    .rv-stars > span { position: absolute; left: 0; top: 0; overflow: hidden; white-space: nowrap; color: #e47911; }
    .rv-bar { display: block; height: 18px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; overflow: hidden; }
    .rv-bar > span { display: block; height: 100%; background: #e47911; }
</style>
<script>
(function () {
    function toggle(button, panel) {
        if (!button || !panel) return;
        button.addEventListener('click', function () {
            var open = panel.classList.toggle('hidden') === false;
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    toggle(document.getElementById('review-total'), document.getElementById('review-breakdown'));
    toggle(document.getElementById('review-comments-toggle'), document.getElementById('review-comments'));
})();
</script>
@endsection

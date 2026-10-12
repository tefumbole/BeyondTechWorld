@extends('beyond.layout')

@section('title', 'Reviews | Beyond Company Limited')
@section('meta_description', 'Reviews from clients of Beyond Company Limited, shown with their country.')

@section('content')
<section class="bg-gradient-to-r from-brand-blue via-brand-light to-brand-blue py-8 sm:py-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 text-center">
        <p class="text-brand-gold font-semibold tracking-wide text-sm mb-2">Beyond Company Limited</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-white m-0">Client reviews</h1>
        <p class="text-white/90 mt-3 mb-0 max-w-2xl mx-auto">People who worked with us share their country, a photo, and a signature. A review appears here when it is 5 stars.</p>
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
                            </div>
                        </div>
                        <div class="mt-3 text-brand-gold tracking-wide" aria-label="5 stars">★★★★★</div>
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
</section>
@endsection

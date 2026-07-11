<section class="bg-gradient-to-r from-brand-blue via-brand-light to-brand-blue py-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">{!! $title !!}</h1>
        @if (!empty($subtitle))
            <p class="text-xl text-gray-200">{{ $subtitle }}</p>
        @endif
    </div>
</section>

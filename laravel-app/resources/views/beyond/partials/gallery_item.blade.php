@php $card = \App\Support\GalleryEmbed::cardData($item); @endphp

<div class="bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden flex flex-col h-full">
    <div class="relative bg-gray-100 flex items-center justify-center overflow-hidden min-h-[280px]">
        @if ($item->type === 'image' && $card['file_url'])
            <img src="{{ $card['file_url'] }}" alt="{{ $item->title ?: 'Gallery image' }}"
                 class="w-full h-full object-cover max-h-[420px] cursor-zoom-in gallery-lightbox-trigger"
                 data-full="{{ $card['file_url'] }}">
        @elseif ($item->type === 'video' && $card['file_url'])
            <video controls playsinline class="w-full max-h-[420px] bg-black">
                <source src="{{ $card['file_url'] }}">
            </video>
        @elseif ($item->type === 'audio' && $card['file_url'])
            <div class="p-8 w-full text-center">
                <i data-lucide="music" class="w-16 h-16 text-brand-gold mx-auto mb-4"></i>
                <audio controls class="w-full">
                    <source src="{{ $card['file_url'] }}">
                </audio>
            </div>
        @elseif (in_array($item->type, ['youtube', 'youtube_short']) && !empty($card['youtube_id']))
            <div class="w-full aspect-video">
                <iframe class="w-full h-full" src="https://www.youtube.com/embed/{{ $card['youtube_id'] }}"
                        title="{{ $item->title ?: 'YouTube video' }}" frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        @elseif ($item->type === 'tiktok' && !empty($card['tiktok_id']))
            <blockquote class="tiktok-embed mx-auto" cite="{{ $item->media_url }}" data-video-id="{{ $card['tiktok_id'] }}"
                        style="max-width:605px;min-width:325px;">
                <section>
                    <a target="_blank" rel="noopener noreferrer" href="{{ $item->media_url }}">{{ $item->title ?: 'TikTok video' }}</a>
                </section>
            </blockquote>
        @elseif ($item->type === 'instagram' && !empty($card['instagram_path']))
            <blockquote class="instagram-media mx-auto" data-instgrm-permalink="https://www.instagram.com/{{ $card['instagram_path'] }}/"
                        data-instgrm-version="14" style="max-width:540px;min-width:326px;width:100%;"></blockquote>
        @elseif ($item->type === 'facebook' && $item->media_url)
            <div class="w-full aspect-video">
                <iframe class="w-full h-full" src="https://www.facebook.com/plugins/video.php?href={{ urlencode($item->media_url) }}&show_text=false"
                        style="border:none;overflow:hidden" scrolling="no" frameborder="0"
                        allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
            </div>
        @elseif ($item->media_url)
            <a href="{{ $item->media_url }}" target="_blank" rel="noopener"
               class="text-brand-blue font-semibold p-8 text-center hover:underline">
                View media <i data-lucide="external-link" class="w-4 h-4 inline"></i>
            </a>
        @else
            <div class="p-8 text-gray-400 text-center">Media unavailable</div>
        @endif
    </div>

    <div class="p-5 flex-grow">
        @if ($item->title)
            <h3 class="text-lg font-bold text-brand-blue mb-2">{{ $item->title }}</h3>
        @endif
        @if ($item->description)
            <p class="text-gray-600 text-sm leading-relaxed">{{ $item->description }}</p>
        @endif
        <span class="inline-block mt-3 text-xs font-semibold uppercase tracking-wide text-brand-gold">
            {{ \App\Support\GalleryEmbed::types()[$item->type] ?? $item->type }}
        </span>
    </div>
</div>

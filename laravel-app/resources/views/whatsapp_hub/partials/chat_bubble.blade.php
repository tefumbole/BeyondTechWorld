@php
    $text = trim((string) ($text ?? ''));
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{2,}/", "\n", $text);
    $type = strtoupper((string) ($type ?? 'TEXT'));
    $mediaUrl = (string) ($mediaUrl ?? '');
    $mediaName = (string) ($mediaName ?? '');
    $tick = (string) ($tick ?? '');
    $fileLabel = $mediaName !== '' ? $mediaName : ($type === 'IMAGE' ? 'Photo' : ($type === 'AUDIO' ? 'Voice message' : ($type === 'VIDEO' ? 'Video' : ($type === 'LOCATION' ? 'Location' : 'Document'))));
@endphp
<div class="bubble {{ !empty($out) ? 'out' : 'in' }}" @if(!empty($id)) data-id="{{ (int) $id }}" @endif @if(!empty($day)) data-day="{{ $day }}" @endif>
    @if(!empty($who))<span class="who">{{ $who }}</span>@endif
    @if($type === 'IMAGE' && $mediaUrl !== '')
        <img class="chat-photo" src="{{ $mediaUrl }}" alt="{{ $fileLabel }}" loading="lazy" decoding="async" onerror="this.replaceWith(document.createTextNode(this.alt||'Photo'))">
    @elseif($type === 'AUDIO' && $mediaUrl !== '')
        <audio controls preload="none" src="{{ $mediaUrl }}"></audio>
    @elseif($type === 'VIDEO' && $mediaUrl !== '')
        <video controls preload="none" src="{{ $mediaUrl }}"></video>
    @elseif($mediaUrl !== '' && in_array($type, ['DOCUMENT', 'LOCATION'], true))
        <a class="chat-file" href="{{ $mediaUrl }}" target="_blank" rel="noopener">{{ $fileLabel }}</a>
    @elseif(in_array($type, ['IMAGE', 'AUDIO', 'VIDEO', 'DOCUMENT', 'LOCATION'], true) && $text === '')
        <span>{{ $fileLabel }}</span>
    @endif
    @if($text !== ''){{ $text }}@endif
    @if(!empty($choices))
        <div class="choices">
            @foreach($choices as $choice)
                <div class="choice">{{ $choice }}</div>
            @endforeach
        </div>
    @endif
    <time>{{ $time ?? '' }}@if(!empty($out))<span class="tick {{ $tick }}">@if($tick === 'failed')!@elseif(in_array($tick, ['read', 'played', 'delivered'], true))✓✓@else ✓@endif</span>@endif</time>
</div>

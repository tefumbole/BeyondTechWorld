@php
    $text = trim((string) ($text ?? ''));
    $text = preg_replace("/[ \t]+\n/", "\n", $text);
    $text = preg_replace("/\n{2,}/", "\n", $text);
    $type = strtoupper((string) ($type ?? 'TEXT'));
    $mediaUrl = (string) ($mediaUrl ?? '');
    $mediaName = (string) ($mediaName ?? '');
    $tick = (string) ($tick ?? '');
    $fileLabel = $mediaName !== '' ? $mediaName : ($type === 'IMAGE' ? 'Photo' : ($type === 'AUDIO' ? 'Voice message' : ($type === 'VIDEO' ? 'Video' : ($type === 'LOCATION' ? 'Location' : 'Document'))));
    $linkify = function ($value) {
        $value = (string) $value;
        $parts = preg_split('/(https?:\/\/[^\s<]+|www\.[^\s<]+)/iu', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (! is_array($parts)) {
            return e($value);
        }
        $html = '';
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $display = rtrim($part, '.,);]');
                $tail = substr($part, strlen($display));
                $href = stripos($display, 'www.') === 0 ? 'https://'.$display : $display;
                $html .= '<a class="chat-link" href="'.e($href).'" target="_blank" rel="noopener">'.e($display).'</a>'.e($tail);
            } else {
                $html .= e($part);
            }
        }

        return $html;
    };
@endphp
<div class="bubble {{ !empty($out) ? 'out' : 'in' }}{{ !empty($deleted) ? ' is-deleted' : '' }}{{ !empty($starred) ? ' is-star' : '' }}" @if(!empty($id)) data-id="{{ (int) $id }}" @endif @if(!empty($out)) data-out="1" @endif @if(!empty($canEdit)) data-edit="1" @endif @if(!empty($canDrop)) data-drop="1" @endif @if(!empty($starred)) data-star="1" @endif @if(!empty($deleted)) data-deleted="1" @endif @if(!empty($voicePending)) data-voice="{{ (int) $id }}" @endif @if(!empty($at)) data-at="{{ $at }}" @endif @if(!empty($day)) data-day="{{ $day }}" @endif>
    @if(!empty($who))<span class="who">{{ $who }}</span>@endif
    @if(!empty($forwarded))<span class="forwarded">Forwarded</span>@endif
    @if(!empty($quote))<span class="quote">{!! $linkify($quote) !!}</span>@endif
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
    @if($text !== '')<span class="body">{!! $linkify($text) !!}</span>@endif
    @if(!empty($aside))<span class="aside">{!! $linkify($aside) !!}</span>@endif
    @if(!empty($choices))
        <div class="choices">
            @foreach($choices as $choice)
                <div class="choice">{{ $choice }}</div>
            @endforeach
        </div>
    @endif
    <time @if(!empty($at)) data-at="{{ $at }}" @endif><span class="clock">{{ $time ?? '' }}</span>@if(!empty($edited))<span class="edited">Edited</span>@endif @if(!empty($starred))<span class="star">★</span>@endif @if(!empty($out))<span class="tick {{ $tick }}">@if($tick === 'failed')! Not sent@elseif($tick === 'read' || $tick === 'played')✓✓ Read@elseif($tick === 'delivered')✓✓ Delivered@elseif($tick === 'sent')✓ Sent@elseif($tick === 'queued')Sending@else ✓@endif</span>@endif</time>
</div>

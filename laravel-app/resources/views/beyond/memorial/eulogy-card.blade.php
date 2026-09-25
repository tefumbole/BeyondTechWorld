@php
    $name = trim((string) ($eu['name'] ?? ''));
    $letter = $name === '' ? '·' : strtoupper(substr($name, 0, 1));
@endphp
<article class="eu-card" id="tribute-{{ $num }}">
    <div class="eu-top">
        <div class="eu-avatar" aria-hidden="true">
            @if(!empty($eu['has_selfie']))
                <img src="{{ $eu['selfie'] }}" alt="">
            @else
                {{ $letter }}
            @endif
        </div>
        <div class="eu-byline">
            <h2>{{ $eu['name'] }}</h2>
            @if(!empty($eu['when']))
                <time>{{ $eu['when'] }}</time>
            @endif
        </div>
        <span class="eu-num">{{ sprintf('%02d', $num) }}</span>
    </div>
    <div class="eu-body">
        @foreach(($eu['paragraphs'] ?? [$eu['body']]) as $para)
            <p>{{ $para }}</p>
        @endforeach
        @if(!empty($eu['has_signature']))
            <div class="eu-sign">
                <cite>{{ $eu['name'] }}</cite>
                <img src="{{ $eu['signature'] }}" alt="Signature of {{ $eu['name'] }}">
            </div>
        @endif
    </div>
</article>

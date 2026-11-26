@php
    $langTarget = isset($target) ? $target : '';
    $langSelected = isset($selected) ? (string) $selected : '';
    $langSubmit = ! empty($submit) ? '1' : '0';
    $langAll = isset($translatorLanguages) && is_array($translatorLanguages) ? $translatorLanguages : [];
    $langHints = isset($langHint) && is_array($langHint) ? $langHint : [];
    $englishName = isset($langAll['en']) ? $langAll['en'] : 'English';
    unset($langAll['en']);
@endphp
<button type="button" class="lang-option {{ $langSelected === 'en' ? 'is-on' : '' }}" data-code="en" data-target="{{ $langTarget }}" @if($langSubmit === '1') data-submit="1" @endif data-search="english default en">{{ $englishName }}</button>
@foreach($langAll as $code => $name)
    <button type="button" class="lang-option {{ $langSelected === (string) $code ? 'is-on' : '' }}" data-code="{{ $code }}" data-target="{{ $langTarget }}" @if($langSubmit === '1') data-submit="1" @endif data-search="{{ strtolower($name.' '.(isset($langHints[$code]) ? $langHints[$code] : '')) }}">{{ $name }}</button>
@endforeach

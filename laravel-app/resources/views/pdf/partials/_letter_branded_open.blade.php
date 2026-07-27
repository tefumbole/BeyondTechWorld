@php
    $useSystemLetterhead = ! empty($use_system_letterhead);
    $letterheadFlow = ! empty($letterhead_flow);
    $letterhead = $letterhead ?? \App\Support\Letterhead::resolve($general_setting ?? null);
    $hasLetterhead = ! empty($letterhead['has_header']) && (
        $useSystemLetterhead || (($general_setting->invoice_format ?? '') == 'beyond_a4')
    );
    $watermarkPath = ! empty($letterhead['watermark_path']) ? $letterhead['watermark_path'] : null;
@endphp

@if($watermarkPath && file_exists($watermarkPath))
    <div class="letter-watermark">
        <img src="{{ $watermarkPath }}" alt="">
    </div>
@endif

@if($hasLetterhead && ! empty($letterhead['header_path']) && ! $letterheadFlow)
    <img src="{{ $letterhead['header_path'] }}" class="letter-header-img" alt="">
@endif

<div class="letter-page {{ $hasLetterhead ? 'has-letterhead' : '' }}">
@if($hasLetterhead && ! empty($letterhead['header_path']) && $letterheadFlow)
    <img src="{{ $letterhead['header_path'] }}" class="letter-header-img" alt="">
@endif

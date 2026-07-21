</div>

@php
    $useSystemLetterhead = ! empty($use_system_letterhead);
    $letterhead = $letterhead ?? \App\Support\Letterhead::resolve($general_setting ?? null);
    $hasLetterFooter = ! empty($letterhead['has_footer']) && (
        $useSystemLetterhead || (($general_setting->invoice_format ?? '') == 'beyond_a4')
    );
@endphp
@if($hasLetterFooter && ! empty($letterhead['footer_path']))
    <img src="{{ $letterhead['footer_path'] }}" class="letter-footer-img" alt="">
@endif

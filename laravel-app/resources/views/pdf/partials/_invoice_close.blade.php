@php
    $invoiceLetterhead = $invoiceLetterhead ?? \App\Support\Letterhead::ensureSynced();
    $invoiceHasFooter = ! empty($invoiceLetterhead['has_footer']) && ! empty($invoiceLetterhead['footer_path']) && file_exists($invoiceLetterhead['footer_path']);
@endphp
@if($invoiceHasFooter)
    <img src="{{ $invoiceLetterhead['footer_path'] }}" class="inv-footer-img" alt="">
@endif

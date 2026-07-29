@php
    $invoiceLetterhead = $invoiceLetterhead ?? \App\Support\Letterhead::ensureSynced();
    $invoiceHasFooter = ! empty($invoiceLetterhead['has_footer']) && ! empty($invoiceLetterhead['footer_path']) && file_exists($invoiceLetterhead['footer_path']);
@endphp
@if($invoiceHasFooter)
    <img src="{{ \App\Support\Letterhead::pdfImage($invoiceLetterhead['footer_path'], 1400, true) }}" class="inv-footer-img" alt="">
@endif

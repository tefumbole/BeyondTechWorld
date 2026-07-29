@php
    $invoiceLetterhead = $invoiceLetterhead ?? \App\Support\Letterhead::ensureSynced();
    $invoiceHasHeader = ! empty($invoiceLetterhead['has_header']) && ! empty($invoiceLetterhead['header_path']) && file_exists($invoiceLetterhead['header_path']);
    $invoiceWatermark = ! empty($invoiceLetterhead['watermark_path']) && file_exists($invoiceLetterhead['watermark_path'])
        ? \App\Support\Letterhead::pdfImage($invoiceLetterhead['watermark_path'], 500)
        : null;
@endphp
@if($invoiceWatermark)
    <div class="inv-watermark"><img src="{{ $invoiceWatermark }}" alt=""></div>
@endif
@if($invoiceHasHeader)
    <img src="{{ \App\Support\Letterhead::pdfImage($invoiceLetterhead['header_path'], 1400, true) }}" class="inv-header-img" alt="">
@endif

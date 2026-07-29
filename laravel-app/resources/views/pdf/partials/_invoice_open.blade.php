@php
    $invoiceLetterhead = $invoiceLetterhead ?? \App\Support\Letterhead::ensureSynced();
    $invoiceHasHeader = ! empty($invoiceLetterhead['has_header']) && ! empty($invoiceLetterhead['header_path']) && file_exists($invoiceLetterhead['header_path']);
    $invoiceWatermark = ! empty($invoiceLetterhead['watermark_path']) && file_exists($invoiceLetterhead['watermark_path'])
        ? $invoiceLetterhead['watermark_path']
        : null;
@endphp
@if($invoiceWatermark)
    <div class="inv-watermark"><img src="{{ $invoiceWatermark }}" alt=""></div>
@endif
@if($invoiceHasHeader)
    <img src="{{ $invoiceLetterhead['header_path'] }}" class="inv-header-img" alt="">
@endif

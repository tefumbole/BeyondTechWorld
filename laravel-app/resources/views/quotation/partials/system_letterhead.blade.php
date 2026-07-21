{{-- Browser letterhead from General Settings (same images as letters). --}}
@php
    $letterhead = \App\Support\Letterhead::ensureSynced();
    $quotationLetterhead = ! empty($letterhead['has_header']);
    $quotationLetterFooter = ! empty($letterhead['has_footer']);
    $quotationWatermark = $letterhead['watermark_file'] ?? null;
    $quotationHeaderUrl = $letterhead['header_url'] ?? null;
    $quotationFooterUrl = $letterhead['footer_url'] ?? null;
    $quotationWatermarkUrl = $letterhead['watermark_url'] ?? null;
@endphp

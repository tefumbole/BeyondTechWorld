@php
    // Letters: Beyond A4 + resolvable header. Quotations: set $use_system_letterhead = true.
    // DomPDF often overlaps fixed letterheads — set $letterhead_flow = true for in-flow header/footer.
    $useSystemLetterhead = ! empty($use_system_letterhead);
    $letterheadFlow = ! empty($letterhead_flow);
    $letterhead = $letterhead ?? \App\Support\Letterhead::resolve($general_setting ?? null);
    $hasLetterhead = ! empty($letterhead['has_header'])
        && ($useSystemLetterhead || ($general_setting->invoice_format ?? '') == 'beyond_a4');
@endphp
<style type="text/css">
@if($letterheadFlow)
    @page { margin: 18px 16px 22px 16px; }
@else
    /* Reserve top/bottom page margins for the repeating letterhead & footer
       images so multi-page letters keep the header on top and footer at the
       bottom of every page, and body text never collides with them. */
    @page { margin: {{ $hasLetterhead ? '155px 0 120px 0' : '0' }}; }
@endif
    body {
        margin: 0;
        padding: 0;
        font-family: DejaVu Sans, sans-serif;
        font-size: 13px;
        line-height: 1.45;
        color: #1f2a44;
        position: relative;
    }
    .letter-page {
        position: relative;
        z-index: 2;
        padding: 0 28px 20px;
    }
    .letter-watermark {
        position: fixed;
        top: 32%;
        left: 22%;
        width: 56%;
        z-index: 0;
        opacity: 0.08;
        text-align: center;
    }
    .letter-watermark img {
        width: 100%;
        max-width: 420px;
    }
@if($letterheadFlow)
    .letter-header-img {
        position: relative;
        display: block;
        width: 100%;
        max-height: 110px;
        margin: 0 0 10px 0;
        z-index: 1;
    }
    .letter-footer-img {
        position: relative;
        display: block;
        width: 100%;
        max-height: 80px;
        margin: 18px 0 0 0;
        z-index: 1;
        page-break-inside: avoid;
    }
    .letter-page.has-letterhead {
        padding-top: 0;
    }
@else
    /* Fixed positioning makes dompdf repeat these on every page. */
    .letter-header-img {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        width: 100%;
        display: block;
        z-index: 1;
    }
    .letter-footer-img {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        display: block;
        z-index: 1;
    }
@endif
    .letter-top-right {
        text-align: right;
        margin: 0 0 6px;
        min-height: 8px;
    }
    .letter-corner-header {
        text-align: right;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;
        margin: 0 0 4px;
        color: #1f2a44;
    }
    .letter-corner-header p,
    .letter-corner-header div {
        margin: 0;
        padding: 0;
        text-align: right;
    }
    .letter-corner-stamp {
        display: inline-block;
        text-align: center;
        margin: 0 0 0 4px;
        vertical-align: top;
        max-width: 48px;
    }
    .letter-corner-stamp img {
        /* Comment / Approver marks — keep very small on printed letters */
        height: 14px;
        max-height: 14px;
        max-width: 48px;
        width: auto;
        display: block;
        margin-left: auto;
        background: transparent;
    }
    .letter-meta { margin: 8px 0 12px; font-size: 13px; clear: both; }
    .letter-to-block { margin: 0 0 10px; line-height: 1.35; }
    .letter-dear { margin: 0 0 12px; font-weight: 600; }
    .letter-content-header { margin: 0 0 10px; font-size: 12px; }
    .letter-body {
        position: relative;
        z-index: 2;
    }
    .letter-body h2 {
        font-size: 15px;
        margin: 10px 0;
    }
    .letter-signature-row {
        position: relative;
        margin-top: 18px;
        min-height: 120px;
    }
    .letter-signature-left {
        position: relative;
        z-index: 2;
        width: 48%;
    }
    .letter-sincerely {
        margin: 0 0 2px;
        padding: 0;
    }
    .letter-sign-img {
        display: block;
        max-height: 52px;
        width: auto;
        margin: 0 0 1px;
        background: transparent;
    }
    .letter-sign-stamp {
        display: block;
        font-size: 7px;
        line-height: 1.15;
        color: #555555;
        margin: 0 0 3px;
        font-weight: normal;
    }
    .letter-corner-stamp .letter-sign-stamp {
        font-size: 6px;
        margin: 0;
    }
    .letter-closing {
        margin: 0;
        padding: 0;
        line-height: 1.15;
        font-size: 13px;
    }
    .letter-closing p,
    .letter-closing div,
    .letter-closing h1,
    .letter-closing h2,
    .letter-closing h3,
    .letter-closing h4,
    .letter-closing h5,
    .letter-closing h6 {
        margin: 0;
        padding: 0;
        line-height: 1.15;
        font-size: 13px;
        font-weight: normal;
    }
    .letter-cc {
        margin: 0;
        padding: 0;
        line-height: 1.15;
        font-size: 13px;
    }
    .letter-cc-banner {
        margin: 0 0 10px;
        padding: 6px 10px;
        border: 1px solid #c5d0c8;
        background: #f3f7f4;
        color: #1f3d2f;
        font-size: 12px;
        line-height: 1.35;
    }
    .letter-codes-back {
        position: absolute;
        left: 0;
        right: 0;
        top: 8px;
        z-index: 0;
        text-align: center;
    }
    .letter-codes-back .letter-qr {
        display: block;
        margin: 0 auto 4px;
    }
    .letter-codes-back .letter-barcode {
        display: block;
        margin: 0 auto;
    }
    .letter-footer-text {
        position: relative;
        z-index: 2;
        margin-top: 8px;
        clear: both;
        line-height: 1.15;
    }
    .header-letter { text-align: right; font-size: 10px; }
</style>

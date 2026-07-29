@php
    /**
     * Shared compact A4 layout for quotations, sale invoices and booking/rental
     * invoices. Keeps the delivered (WhatsApp/email) PDF on one page where the
     * data allows, and repeats the branded header/footer on any overflow page.
     *
     * The header/footer images are position:fixed so dompdf paints them on every
     * page; @page margins below reserve exactly enough room for them, derived
     * from each image's real aspect ratio rather than guessed values.
     */
    $invoiceLetterhead = $invoiceLetterhead ?? \App\Support\Letterhead::ensureSynced();
    $invoiceHasHeader = ! empty($invoiceLetterhead['has_header']) && ! empty($invoiceLetterhead['header_path']) && file_exists($invoiceLetterhead['header_path']);
    $invoiceHasFooter = ! empty($invoiceLetterhead['has_footer']) && ! empty($invoiceLetterhead['footer_path']) && file_exists($invoiceLetterhead['footer_path']);

    // A4 at dompdf's 96dpi. Side page margins are 0 so header/footer can bleed
    // edge-to-edge; content is padded inward via body margin.
    $invoicePageWidth = 793.7;
    $invoiceSideMargin = 0;
    $invoiceContentPad = 18;
    $invoiceBandWidth = $invoicePageWidth;

    $invoiceHeaderHeight = $invoiceHasHeader
        ? (int) ceil($invoiceBandWidth * \App\Support\Letterhead::ratio($invoiceLetterhead['header_path'], 0.157))
        : 0;
    $invoiceFooterHeight = $invoiceHasFooter
        ? (int) ceil($invoiceBandWidth * \App\Support\Letterhead::ratio($invoiceLetterhead['footer_path'], 0.093))
        : 0;

    // Keep bands readable on one A4 page while still spanning full page width.
    $invoiceHeaderHeight = $invoiceHeaderHeight ? min($invoiceHeaderHeight, 105) : 0;
    $invoiceFooterHeight = $invoiceFooterHeight ? min($invoiceFooterHeight, 78) : 0;

    $invoiceTopMargin = $invoiceHeaderHeight ? $invoiceHeaderHeight + 6 : 22;
    $invoiceBottomMargin = $invoiceFooterHeight ? $invoiceFooterHeight + 6 : 22;

    // DejaVu is the only stack dompdf renders bold with (its built-in Helvetica
    // ignores font-weight), and it covers non-Latin scripts. Font subsetting is on
    // in config/dompdf.php so this costs a few KB rather than ~1.2 MB.
@endphp
<style type="text/css">
    @page { margin: {{ $invoiceTopMargin }}px 0 {{ $invoiceBottomMargin }}px 0; }

    body {
        margin: 0 {{ $invoiceContentPad }}px;
        padding: 0;
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10.5px;
        line-height: 1.35;
        color: #1f2a44;
    }
    p { margin: 0 0 5px; }

    .inv-header-img,
    .inv-footer-img {
        position: fixed;
        left: 0;
        width: {{ $invoicePageWidth }}px;
        display: block;
    }
    .inv-header-img {
        top: -{{ $invoiceTopMargin }}px;
        height: {{ max(1, $invoiceHeaderHeight) }}px;
    }
    .inv-footer-img {
        bottom: -{{ $invoiceBottomMargin }}px;
        height: {{ max(1, $invoiceFooterHeight) }}px;
    }

    .inv-watermark {
        position: fixed;
        top: 34%;
        left: 26%;
        width: 48%;
        text-align: center;
        opacity: 0.07;
    }
    .inv-watermark img { width: 100%; }

    .inv-title {
        text-align: center;
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 0.4px;
        margin: 0 0 8px;
    }
    .inv-ref {
        text-align: left;
        font-size: 11px;
        color: #1f2a44;
        margin: 0 0 8px;
        line-height: 1.45;
    }
    .inv-ref strong { font-weight: bold; }
    table.inv-meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.inv-meta td {
        width: 50%;
        vertical-align: top;
        padding: 6px 8px;
        border: 1px solid #dfe3ec;
        background: #f8f9fc;
        font-size: 10px;
        line-height: 1.4;
    }
    table.inv-meta .inv-label {
        display: block;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #1f2a44;
        margin-bottom: 3px;
    }
    table.inv-meta .inv-name { font-weight: bold; }
    .inv-schedule {
        border: 1px solid #b7d4c4;
        background: #f2f8f4;
        padding: 7px 9px;
        margin: 0 0 8px;
        font-size: 10.5px;
        line-height: 1.5;
        page-break-inside: avoid;
    }
    .inv-schedule strong { color: #1f3d32; }
    .inv-codes-block { margin-top: 8px; page-break-inside: avoid; text-align: left; }
    .inv-codes-block .inv-created { font-size: 10px; line-height: 1.4; margin-bottom: 6px; text-align: left; }
    .inv-codes-block .inv-qr,
    .inv-codes-block .inv-barcode { text-align: center; }
    .inv-codes-block img { display: block; margin: 0 auto; }

    /* Two-column meta strip: issuer on the left, recipient on the right. */
    table.inv-parties { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.inv-parties td {
        width: 50%;
        vertical-align: top;
        padding: 7px 9px;
        border: 1px solid #dfe3ec;
        background: #f8f9fc;
    }
    .inv-parties .inv-label {
        display: block;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #1f2a44;
        margin-bottom: 3px;
    }
    /* Alias: same meta strip used by sales / booking / rental / quotation */
    .inv-parties .inv-name { font-weight: bold; }

    /* Line items. Column widths come from a <colgroup> in each template. */
    table.inv-items { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    table.inv-items thead th {
        background: #d9ebe1;
        color: #1f3d32;
        border-bottom: 1px solid #b7d4c4;
        border-top: 1px solid #b7d4c4;
        padding: 5px 6px;
        text-align: left;
        font-size: 9.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    table.inv-items tbody td {
        padding: 5px 6px;
        border-bottom: 1px solid #e3efe8;
        vertical-align: top;
    }
    table.inv-items tbody tr { page-break-inside: avoid; }
    table.inv-items tbody tr.inv-alt td { background: #f2f8f4; }
    table.inv-items tbody tr.inv-total td { background: #e5f2eb; font-weight: bold; }
    .inv-num, .inv-qty { text-align: center; }
    .inv-money { text-align: right; }
    .inv-sub { display: block; font-size: 9px; color: #6b7386; }

    /* Totals sit in a right-hand column so the page width is actually used. */
    table.inv-summary { width: 100%; border-collapse: collapse; margin-top: 8px; page-break-inside: avoid; }
    table.inv-summary > tbody > td { vertical-align: top; }
    .inv-summary-left { width: 56%; vertical-align: top; padding-right: 12px; }
    .inv-summary-right { width: 44%; vertical-align: top; }

    table.inv-totals { width: 100%; border-collapse: collapse; }
    table.inv-totals th {
        text-align: left;
        font-weight: normal;
        padding: 4px 6px;
        border-bottom: 1px solid #eceef4;
    }
    table.inv-totals td {
        text-align: right;
        padding: 4px 6px;
        border-bottom: 1px solid #eceef4;
        white-space: nowrap;
    }
    table.inv-totals tr.inv-grand th,
    table.inv-totals tr.inv-grand td {
        font-size: 12px;
        font-weight: bold;
        color: #1f3d32;
        background: #e5f2eb;
        border-top: 1px solid #b7d4c4;
        border-bottom: 1px solid #b7d4c4;
    }

    .inv-box {
        border: 1px solid #dfe3ec;
        padding: 6px 8px;
        margin-bottom: 7px;
    }
    .inv-box .inv-label {
        display: block;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #1f2a44;
        margin-bottom: 2px;
    }
    .inv-words { font-style: italic; }
    .inv-note p:last-child { margin-bottom: 0; }
    .inv-note ul, .inv-note ol { margin: 3px 0 3px 14px; padding: 0; }

    .inv-status {
        display: inline-block;
        padding: 1px 6px;
        border: 1px solid #c9cfdf;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
    }

    /* Kept inside the summary block: as a trailing element of its own it could be
       pushed onto an otherwise blank final page. */
    table.inv-foot-row { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.inv-foot-row td { vertical-align: middle; padding: 0; }
    .inv-codes {
        width: 42%;
        text-align: right;
        white-space: nowrap;
    }
    .inv-codes img { vertical-align: middle; }
    .inv-thanks {
        width: 58%;
        font-size: 9.5px;
        color: #6b7386;
    }
</style>

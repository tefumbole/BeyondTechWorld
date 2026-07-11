@php
    $hasLetterhead = ($general_setting->invoice_format ?? '') == 'beyond_a4' && !empty($general_setting->email_header);
@endphp
<style type="text/css">
    /* Reserve top/bottom page margins for the repeating letterhead & footer
       images so multi-page letters keep the header on top and footer at the
       bottom of every page, and body text never collides with them. */
    @page { margin: {{ $hasLetterhead ? '155px 0 120px 0' : '0' }}; }
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
    .letter-meta { margin: 18px 0; }
    .letter-body {
        position: relative;
        z-index: 2;
    }
    .letter-body h2 {
        font-size: 15px;
        margin: 12px 0;
    }
    .letter-signature-row {
        position: relative;
        margin-top: 28px;
        min-height: 150px;
    }
    .letter-signature-left {
        position: relative;
        z-index: 2;
        width: 45%;
    }
    .letter-codes-back {
        position: absolute;
        left: 0;
        right: 0;
        top: 10px;
        z-index: 0;
        text-align: center;
    }
    .letter-codes-back img {
        display: inline-block;
        margin: 0 auto;
    }
    .letter-footer-text {
        position: relative;
        z-index: 2;
        margin-top: 24px;
        clear: both;
    }
    .header-letter { text-align: right; font-size: 10px; }
    .edit, .approve {
        position: absolute;
        margin-top: -20px;
        z-index: 0;
        opacity: 0.5;
    }
    .edit { margin-left: 75px; }
    .approve { margin-left: 30px; }
</style>

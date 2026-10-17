@php
    $useSystemLetterhead = ! empty($use_system_letterhead);
    $letterheadFlow = ! empty($letterhead_flow);
    $organizationLetterhead = isset($data) && is_object($data) && ! empty($data->organization_id);
    $letterhead = $letterhead ?? (
        $organizationLetterhead
            ? \App\Support\Letterhead::forOrganization($data->organization ?? \App\LetterOrganization::find($data->organization_id))
            : \App\Support\Letterhead::resolve($general_setting ?? null)
    );
    $hasLetterhead = ! empty($letterhead['has_header']) && (
        $useSystemLetterhead || $organizationLetterhead || (($general_setting->invoice_format ?? '') == 'beyond_a4')
    );
    $watermarkPath = ! empty($letterhead['watermark_path']) ? $letterhead['watermark_path'] : null;
@endphp

@if($watermarkPath && file_exists($watermarkPath))
    <div class="letter-watermark">
        <img src="{{ \App\Support\Letterhead::pdfImage($watermarkPath, 500) }}" alt="">
    </div>
@endif

@php
    $headerPdf = ($hasLetterhead && ! empty($letterhead['header_path']))
        ? \App\Support\Letterhead::pdfImage($letterhead['header_path'], 1400, true)
        : null;
@endphp
@if($headerPdf && ! $letterheadFlow)
    <img src="{{ $headerPdf }}" class="letter-header-img" alt="">
@endif

<div class="letter-page {{ $hasLetterhead ? 'has-letterhead' : '' }}">
@if($headerPdf && $letterheadFlow)
    <img src="{{ $headerPdf }}" class="letter-header-img" alt="">
@endif

@php
    $useSystemLetterhead = ! empty($use_system_letterhead);
    $letterheadFlow = ! empty($letterhead_flow);
    $organizationLetterhead = isset($data) && is_object($data) && ! empty($data->organization_id);
    $letterhead = $letterhead ?? (
        $organizationLetterhead
            ? \App\Support\Letterhead::forOrganization($data->organization ?? \App\LetterOrganization::find($data->organization_id))
            : \App\Support\Letterhead::resolve($general_setting ?? null)
    );
    $hasLetterFooter = ! empty($letterhead['has_footer']) && (
        $useSystemLetterhead || $organizationLetterhead || (($general_setting->invoice_format ?? '') == 'beyond_a4')
    );
@endphp
@php
    $footerPdf = ($hasLetterFooter && ! empty($letterhead['footer_path']))
        ? \App\Support\Letterhead::pdfImage($letterhead['footer_path'], 1400, true)
        : null;
@endphp
@if($footerPdf && $letterheadFlow)
    <img src="{{ $footerPdf }}" class="letter-footer-img" alt="">
@endif
</div>
@if($footerPdf && ! $letterheadFlow)
    <img src="{{ $footerPdf }}" class="letter-footer-img" alt="">
@endif

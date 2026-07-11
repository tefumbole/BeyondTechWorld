@php
    $watermarkFile = !empty($general_setting->email_water_mark)
        ? $general_setting->email_water_mark
        : $general_setting->site_logo;
    $watermarkPath = $watermarkFile ? public_path('logo/' . $watermarkFile) : null;
    $hasLetterhead = $general_setting->invoice_format == 'beyond_a4' && !empty($general_setting->email_header);
@endphp

@if($watermarkPath && file_exists($watermarkPath))
    <div class="letter-watermark">
        <img src="{{ $watermarkPath }}" alt="">
    </div>
@endif

@if($general_setting->invoice_format == 'beyond_a4' && !empty($general_setting->email_header))
    <img src="{{ public_path('logo/') . $general_setting->email_header }}" class="letter-header-img" alt="">
@endif

<div class="letter-page {{ $hasLetterhead ? 'has-letterhead' : '' }}">

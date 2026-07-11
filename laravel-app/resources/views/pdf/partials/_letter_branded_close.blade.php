</div>

@if($general_setting->invoice_format == 'beyond_a4' && !empty($general_setting->email_footer))
    <img src="{{ public_path('logo/') . $general_setting->email_footer }}" class="letter-footer-img" alt="">
@endif

@extends('layout.main') @section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('not_permitted') !!}</div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>Messaging Settings</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>Configure WhatsApp and SMS. Values are saved to the <code>.env</code> file.</small></p>
                        <div class="alert alert-info">
                            <strong>Routing policy:</strong>
                            <ul class="mb-0 pl-3">
                                <li><strong>Wasender</strong> — all WhatsApp text/OTP/announcements when provider is Wasender.</li>
                                <li><strong>Twilio</strong> — OTP, announcements, and status texts use Content Templates when provider is Twilio. Admission/hired uses the admission SID.</li>
                                <li>Keep Wasender credentials filled for PDF attachments and optional fallback while you test Twilio.</li>
                            </ul>
                        </div>
                        {!! Form::open(['route' => 'setting.messagingStore', 'method' => 'post']) !!}
                            <h5 class="mb-3">Channels</h5>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Enable WhatsApp</label>
                                        <select class="form-control" name="whatsapp_enabled">
                                            <option value="true" @if($whatsappEnabled) selected @endif>Enabled</option>
                                            <option value="false" @if(! $whatsappEnabled) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Enable SMS</label>
                                        <select class="form-control" name="sms_enabled">
                                            <option value="true" @if($smsEnabled) selected @endif>Enabled</option>
                                            <option value="false" @if(! $smsEnabled) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-3">WhatsApp provider</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Provider *</label>
                                        <select class="form-control" name="whatsapp_service" id="whatsapp_service">
                                            <option value="TWILIO" @if($whatsappService === 'TWILIO') selected @endif>Twilio (beyond_notice — all messages)</option>
                                            <option value="WASENDER" @if($whatsappService === 'WASENDER') selected @endif>Wasender (free-form)</option>
                                        </select>
                                        <small class="text-muted">Twilio sends every text/OTP/announcement through the beyond_notice Content Template. Clear or comment Wasender keys for Twilio-only.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Default country code</label>
                                        <input type="text" name="whatsapp_default_country_code" class="form-control" value="{{ $defaultCountryCode }}" placeholder="237">
                                    </div>
                                    <div class="form-group">
                                        <label>Company name</label>
                                        <input type="text" name="company_name" class="form-control" value="{{ $companyName }}">
                                    </div>
                                    <div class="form-group">
                                        <label>When Twilio fails, fall back to Wasender</label>
                                        <select class="form-control" name="twilio_fallback_wasender">
                                            <option value="false" @if(! $twilioFallback) selected @endif>No (Twilio-only)</option>
                                            <option value="true" @if($twilioFallback) selected @endif>Yes</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div id="wasender-fields" class="row mt-2">
                                <div class="col-md-12"><h6>Wasender credentials (optional when Twilio-only)</h6></div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>WASENDER_API_KEY</label>
                                        <input type="text" name="wasender_api_key" class="form-control" value="{{ $wasenderApiKey }}" autocomplete="off">
                                    </div>
                                    <div class="form-group">
                                        <label>WASENDER_SESSION_ID</label>
                                        <input type="text" name="wasender_session_id" class="form-control" value="{{ $wasenderSessionId }}">
                                    </div>
                                    <div class="form-group">
                                        <label>WASENDER_BASE_URL</label>
                                        <input type="text" name="wasender_base_url" class="form-control" value="{{ $wasenderBaseUrl }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Min send interval (ms)</label>
                                        <input type="number" name="wasender_min_send_interval_ms" class="form-control" value="{{ $wasenderMinInterval }}">
                                    </div>
                                    <div class="form-group">
                                        <label>Text-to-document delay (ms)</label>
                                        <input type="number" name="wasender_text_to_document_delay_ms" class="form-control" value="{{ $wasenderDocDelay }}">
                                    </div>
                                </div>
                            </div>

                            <div id="twilio-wa-fields" class="row mt-2">
                                <div class="col-md-12"><h6>Twilio WhatsApp credentials</h6>
                                    <p class="text-muted small">beyond_notice template: @{{1}} headline, @{{2}} name, @{{3}} message, @{{4}} reference, @{{5}} extra. SID defaults to HX47150e179fdbab79738d060fb0ac6415.</p>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>TWILIO_SID (Account SID)</label>
                                        <input type="text" name="twilio_sid" class="form-control" value="{{ $twilioSid }}" autocomplete="off">
                                    </div>
                                    <div class="form-group">
                                        <label>TWILIO_AUTH_TOKEN</label>
                                        <input type="text" name="twilio_auth_token" class="form-control" value="{{ $twilioAuthToken }}" autocomplete="off">
                                    </div>
                                    <div class="form-group">
                                        <label>TWILIO_WHATSAPP_FROM</label>
                                        <input type="text" name="twilio_whatsapp_from" class="form-control" value="{{ $twilioWhatsappFrom }}" placeholder="whatsapp:+14155238886">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Status / beyond_notice Content SID *</label>
                                        <input type="text" name="twilio_content_sid_status" class="form-control" value="{{ $contentSidStatus }}" placeholder="HX47150e179fdbab79738d060fb0ac6415">
                                        <small class="text-muted">Used for OTP, announcements, quotations, bookings, and all generic texts.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Admission Content SID</label>
                                        <input type="text" name="twilio_content_sid_admission" class="form-control" value="{{ $contentSidAdmission }}">
                                        <small class="text-muted">Defaults to the same beyond_notice SID (mapped into the 5 variables).</small>
                                    </div>
                                    <div class="form-group">
                                        <label>OTP Content SID (optional)</label>
                                        <input type="text" name="twilio_content_sid_otp" class="form-control" value="{{ $contentSidOtp }}" placeholder="Leave empty to reuse beyond_notice">
                                        <small class="text-muted">If empty, OTP uses the Status / beyond_notice SID.</small>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3">SMS provider</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Gateway *</label>
                                        <select class="form-control" name="sms_gateway" id="sms_gateway">
                                            <option value="twilio" @if($smsGateway === 'twilio') selected @endif>Twilio</option>
                                            <option value="clickatell" @if($smsGateway === 'clickatell') selected @endif>Clickatell</option>
                                        </select>
                                    </div>
                                    <div class="form-group sms-twilio">
                                        <label>ACCOUNT_SID</label>
                                        <input type="text" name="account_sid" class="form-control" value="{{ $accountSid }}" autocomplete="off">
                                    </div>
                                    <div class="form-group sms-twilio">
                                        <label>AUTH_TOKEN</label>
                                        <input type="text" name="auth_token" class="form-control" value="{{ $authToken }}" autocomplete="off">
                                    </div>
                                    <div class="form-group sms-twilio">
                                        <label>TWILIO_NUMBER (SMS from)</label>
                                        <input type="text" name="twilio_number" class="form-control" value="{{ $twilioNumber }}">
                                    </div>
                                    <div class="form-group sms-clickatell">
                                        <label>CLICKATELL_API_KEY</label>
                                        <input type="text" name="clickatell_api_key" class="form-control" value="{{ $clickatellApiKey }}" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                                <a href="{{ route('setting.createSms') }}" class="btn btn-outline-secondary">Create / send SMS</a>
                            </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #messaging-setting-menu").addClass("active");

    function toggleWhatsappFields() {
        var v = $('#whatsapp_service').val();
        if (v === 'TWILIO') {
            $('#twilio-wa-fields').show();
            $('#wasender-fields').show();
        } else {
            $('#twilio-wa-fields').hide();
            $('#wasender-fields').show();
        }
    }

    function toggleSmsFields() {
        if ($('#sms_gateway').val() === 'clickatell') {
            $('.sms-twilio').hide();
            $('.sms-clickatell').show();
        } else {
            $('.sms-clickatell').hide();
            $('.sms-twilio').show();
        }
    }

    $('#whatsapp_service').on('change', toggleWhatsappFields);
    $('#sms_gateway').on('change', toggleSmsFields);
    toggleWhatsappFields();
    toggleSmsFields();
</script>
@endsection

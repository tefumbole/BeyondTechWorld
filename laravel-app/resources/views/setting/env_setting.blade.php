@extends('layout.main') @section('content')

@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="mb-0">Environment Files</h4>
                        <span class="text-muted small">{{ $envPath }}</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Edit the <code>.env</code> file directly. After saving, run <code>php artisan config:clear</code> on the server. A timestamped backup is created automatically on each save.</p>
                        {!! Form::open(['route' => 'setting.envStore', 'method' => 'post']) !!}
                            <div class="form-group">
                                <label for="env_content"><strong>Application `.env`</strong></label>
                                <textarea id="env_content" name="env_content" class="form-control" rows="28" style="font-family: Menlo, Monaco, Consolas, monospace; font-size: 13px; line-height: 1.5;">{{ old('env_content', $envContent) }}</textarea>
                            </div>
                            <div class="alert alert-info mb-3">
                                <strong>Messaging:</strong> Prefer
                                <a href="{{ route('setting.messaging') }}">Settings → Messaging Settings</a>
                                to configure WhatsApp (Wasender / Twilio), SMS, and Content SIDs without editing this file.
                                <pre class="mb-0 mt-2" style="font-size: 12px; background: #f8f9fa; padding: 12px; border-radius: 6px;"># WasenderAPI (also editable under Messaging Settings)
# WASENDER_API_KEY=
# WASENDER_SESSION_ID=
WASENDER_BASE_URL=https://wasenderapi.com/api
WASENDER_MIN_SEND_INTERVAL_MS=6000
WASENDER_TEXT_TO_DOCUMENT_DELAY_MS=6000
COMPANY_NAME=Beyond Enterprise
WHATSAPP_SERVICE=TWILIO
WHATSAPP_TWILIO_FALLBACK_WASENDER=false
TWILIO_WHATSAPP_CONTENT_SID_ADMISSION=HX47150e179fdbab79738d060fb0ac6415
TWILIO_WHATSAPP_CONTENT_SID_STATUS=HX47150e179fdbab79738d060fb0ac6415</pre>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Environment File</button>
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
    $("ul#setting #env-setting-menu").addClass("active");
</script>
@endsection

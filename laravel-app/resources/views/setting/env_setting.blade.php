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
                        <h5 class="mb-3">Google Calendar</h5>
                        <p class="text-muted">These fields write the appointment calendar keys into <code>.env</code> and clear the config cache. Leave a secret blank to keep the value already stored.</p>
                        {!! Form::open(['route' => 'setting.envStore', 'method' => 'post']) !!}
                            <input type="hidden" name="save_calendar" value="1">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>GOOGLE_CALENDAR_CLIENT_ID</label>
                                        <input type="text" name="google_calendar_client_id" class="form-control" value="{{ old('google_calendar_client_id', $calendar['client_id']) }}" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>GOOGLE_CALENDAR_ID</label>
                                        <input type="text" name="google_calendar_id" class="form-control" value="{{ old('google_calendar_id', $calendar['calendar_id']) }}" placeholder="primary or the calendar email" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>GOOGLE_CALENDAR_CLIENT_SECRET</label>
                                        <input type="password" name="google_calendar_client_secret" class="form-control" value="" placeholder="{{ $calendar['has_secret'] ? 'Saved. Leave blank to keep it.' : 'Not set' }}" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>GOOGLE_CALENDAR_REFRESH_TOKEN</label>
                                        <input type="password" name="google_calendar_refresh_token" class="form-control" value="" placeholder="{{ $calendar['has_refresh'] ? 'Saved. Leave blank to keep it.' : 'Not set' }}" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>GOOGLE_CALENDAR_CHANNEL_TOKEN</label>
                                        <input type="password" name="google_calendar_channel_token" class="form-control" value="" placeholder="{{ $calendar['has_channel'] ? 'Saved. Leave blank to keep it.' : 'Optional. For inbound calendar notifications.' }}" autocomplete="new-password">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>APPOINTMENT_REMINDERS</label>
                                        <select name="appointment_reminders" class="form-control">
                                            <option value="false" @if(! $calendar['reminders']) selected @endif>Off</option>
                                            <option value="true" @if($calendar['reminders']) selected @endif>On</option>
                                        </select>
                                        <small class="text-muted">WhatsApp reminders stay off until this is On. The server cron must run <code>php artisan schedule:run</code>.</small>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mb-4">Save Google Calendar</button>
                        {!! Form::close() !!}
                        <hr>
                        <p class="text-muted mb-3">Edit the <code>.env</code> file directly. Saving this box replaces the whole file. A timestamped backup is created automatically. Saving the Google Calendar form above already runs <code>php artisan config:clear</code>.</p>
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
WASENDER_API_KEY=your_session_api_key
WASENDER_SESSION_ID=
WASENDER_BASE_URL=https://wasenderapi.com/api
WASENDER_MIN_SEND_INTERVAL_MS=6000
WASENDER_TEXT_TO_DOCUMENT_DELAY_MS=6000
COMPANY_NAME=Beyond Enterprise
WHATSAPP_SERVICE=WASENDER
WHATSAPP_TWILIO_FALLBACK_WASENDER=true
# Google Calendar (also editable in the form above)
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REFRESH_TOKEN=
GOOGLE_CALENDAR_ID=
GOOGLE_CALENDAR_CHANNEL_TOKEN=
APPOINTMENT_REMINDERS=false
# Twilio keys below are for SMS (and optional WhatsApp templates if WHATSAPP_SERVICE=TWILIO)
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

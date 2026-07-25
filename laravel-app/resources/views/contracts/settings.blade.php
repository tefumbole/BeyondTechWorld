@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="mb-4">
            <h1 class="ct-title">Contract Settings</h1>
            <p class="ct-subtitle">Numbering, company identity, admin signer, and engineer rates. Reminders are set per contract (like Task Manager), not here.</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="ct-card">
            <form method="POST" action="{{ route('contracts.settings.update') }}">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Number prefix</label>
                        <input type="text" name="number_prefix" class="ct-field" value="{{ old('number_prefix', $settings['number_prefix'] ?? 'CNT') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Default signature link validity (days)</label>
                        <input type="number" min="1" name="default_validity_days" class="ct-field" value="{{ old('default_validity_days', $settings['default_validity_days'] ?? 14) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Default jurisdiction</label>
                        <input type="text" name="default_jurisdiction" class="ct-field" value="{{ old('default_jurisdiction', $settings['default_jurisdiction'] ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="ct-label">Company legal name</label>
                        <input type="text" name="company_legal_name" class="ct-field" value="{{ old('company_legal_name', $settings['company_legal_name'] ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="ct-label">Default admin signer</label>
                        <select name="default_admin_signer_user_id" class="ct-field">
                            <option value="">— Select user —</option>
                            @foreach($users ?? [] as $u)
                                <option value="{{ $u->id }}" @if((string) old('default_admin_signer_user_id', $settings['default_admin_signer_user_id'] ?? '') === (string) $u->id) selected @endif>
                                    {{ $u->name }} ({{ $u->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="ct-label">Company address</label>
                        <textarea name="company_address" class="ct-field" rows="3">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                    </div>
                </div>

                <h5 class="mt-2 mb-3" style="color:#0b3f90;">Expiry alerts</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="ct-label">Alert window (days before end date)</label>
                        <input type="number" min="1" name="expiry_alert_days" class="ct-field" value="{{ old('expiry_alert_days', $settings['expiry_alert_days'] ?? 30) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label d-flex align-items-center" style="gap:8px;margin-top:28px;">
                            <input type="hidden" name="expiry_alerts_enabled" value="0">
                            <input type="checkbox" name="expiry_alerts_enabled" value="1" @if(old('expiry_alerts_enabled', $settings['expiry_alerts_enabled'] ?? true)) checked @endif>
                            Enable daily expiry scan
                        </label>
                    </div>
                </div>
                <div class="alert alert-light border">
                    <strong>Reminders:</strong> schedule them on each contract with <em>Add reminder</em> (specific date/time), same pattern as Task Manager.
                    Expiry alerts appear on the Dashboard and run daily via <code>contracts:expiry-alerts</code>.
                </div>

                @if(! empty($rates) && count($rates))
                    <h5 class="mt-4 mb-3" style="color:#0b3f90;">Engineer daily rates (XAF)</h5>
                    <div class="row">
                        @foreach($rates as $rate)
                            <div class="col-md-4 mb-3">
                                <label class="ct-label">{{ $rate->name }}</label>
                                <input type="number" name="rates[{{ $rate->id }}]" class="ct-field" value="{{ old('rates.'.$rate->id, $rate->daily_rate) }}">
                            </div>
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="ct-btn"><i class="dripicons-checkmark"></i> Save Settings</button>
            </form>
        </div>
    </div>
</section>
@endsection

@extends('beyond.layout')

@section('title', 'Subscribe')
@section('meta_description', 'Start a Beyond Cloud subscription as an individual or a company.')

@push('head')
<style>
    .step { display: none; text-align: left; }
    .step.on { display: block; }
    .step[data-step="1"] { text-align: center; }
    .subscribe-hero {
        position: relative;
        min-height: calc(100vh - 7.5rem);
        background-size: cover;
        background-position: center;
    }
    .subscribe-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0, 20, 50, .45), rgba(0, 40, 85, .72));
    }
    .subscribe-panel {
        position: relative;
        background: rgba(0, 32, 72, .55);
        border: 1px solid rgba(212, 175, 55, .65);
        box-shadow: 0 18px 50px rgba(0, 0, 0, .28);
        backdrop-filter: blur(8px);
        color: #fff;
    }
    .subscribe-panel h1,
    .subscribe-panel h2,
    .subscribe-panel p,
    .subscribe-panel label,
    .subscribe-panel strong { color: #fff; }
    .subscribe-panel .hint { color: rgba(255,255,255,.88); }
    .subscribe-panel input,
    .subscribe-panel select {
        background: rgba(255,255,255,.96);
        color: #002855;
        border: 0;
        border-radius: 999px;
    }
    .back-step {
        display: inline-flex;
        margin-bottom: 0.75rem;
        background: transparent;
        color: #F5D76E;
        border: 0;
        font-weight: 700;
        padding: 0;
        cursor: pointer;
    }
    .subscribe-choice {
        min-height: 4.5rem;
        border-radius: 999px;
        font-weight: 800;
        font-size: 1.15rem;
    }
    .field-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-top: 0.85rem;
        text-align: left;
    }
    .field-row label {
        flex: 0 0 9.5rem;
        margin: 0;
        font-weight: 650;
        text-align: left;
    }
    .field-row input,
    .field-row select {
        flex: 1 1 auto;
        min-width: 0;
        margin: 0;
        width: auto;
    }
    @media (min-width: 640px) {
        .field-row input,
        .field-row select {
            flex: 0 1 auto;
            width: calc((100% - 10.35rem) * 0.55);
            max-width: calc((100% - 10.35rem) * 0.55);
        }
    }
    .known-name {
        text-align: left;
        font-size: 1.25rem;
        font-weight: 800;
        color: #D4AF37;
        margin-top: 0.4rem;
    }
    .service-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 10px;
    }
    .service-card {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        border-radius: 14px;
        padding: 10px 12px;
        margin: 0;
        border: 2px solid transparent;
        color: #10233f;
        text-align: left;
    }
    .service-card strong,
    .service-card span { color: #10233f !important; }
    .service-card[data-service="SALES_INVOICES"] { background: #dbeafe; border-color: #1d4ed8; }
    .service-card[data-service="RENTALS"] { background: #ccfbf1; border-color: #0f766e; }
    .service-card[data-service="MESSAGING"] { background: #dcfce7; border-color: #15803d; }
    .service-card[data-service="QUOTATIONS"] { background: #fef3c7; border-color: #b45309; }
    .service-card[data-service="DIGITAL_INVITATIONS"] { background: #ede9fe; border-color: #6d28d9; }
    #price-summary { background: #fff; }
    #price-summary,
    #price-summary strong,
    #price-summary span,
    #price-summary p { color: #10233f !important; }
    .stage-rail {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin: 0 0 1.1rem;
        padding: 0;
        list-style: none;
        justify-content: center;
    }
    .stage-rail li {
        margin: 0;
        padding: 0.28rem 0.7rem;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.35);
        color: rgba(255,255,255,.75);
        font-size: 0.78rem;
        font-weight: 700;
    }
    .stage-rail li.on {
        background: #D4AF37;
        border-color: #D4AF37;
        color: #002855;
    }
    .stage-rail li.done { border-color: #F5D76E; color: #F5D76E; }
    .stage-rail li.skip { opacity: .35; }
    .stage-rail.is-track {
        display: flex;
        flex-wrap: nowrap;
        justify-content: space-between;
        gap: 0;
        counter-reset: mai-stage;
        margin-bottom: 1.4rem;
    }
    .stage-rail.is-track li {
        flex: 1 1 0;
        position: relative;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: rgba(255,255,255,.72);
        font-size: 0.68rem;
        padding: 1.7rem 0.1rem 0;
        text-align: center;
    }
    .stage-rail.is-track li::before {
        counter-increment: mai-stage;
        content: counter(mai-stage);
        position: absolute;
        top: 0;
        left: 50%;
        width: 1.35rem;
        height: 1.35rem;
        margin-left: -0.68rem;
        line-height: 1.2rem;
        border-radius: 999px;
        border: 2px solid rgba(255,255,255,.45);
        background: #072f6b;
        color: #fff;
        font-size: 0.72rem;
    }
    .stage-rail.is-track li::after {
        content: "";
        position: absolute;
        top: 0.62rem;
        left: calc(50% + 0.8rem);
        width: calc(100% - 1.6rem);
        height: 2px;
        background: rgba(255,255,255,.28);
    }
    .stage-rail.is-track li:last-child::after { display: none; }
    .stage-rail.is-track li.on,
    .stage-rail.is-track li.done { background: transparent; border: 0; color: #F5D76E; }
    .stage-rail.is-track li.on::before { background: #D4AF37; border-color: #D4AF37; color: #072f6b; }
    .stage-rail.is-track li.done::before { background: #0b3f90; border-color: #F5D76E; color: #F5D76E; }
    .mai-features { list-style: none; margin: 1rem 0 0; padding: 0; display: grid; grid-template-columns: 1fr; gap: 0.55rem; text-align: left; }
    @media (min-width: 640px) { .mai-features { grid-template-columns: 1fr 1fr; } }
    .mai-features li { background: rgba(255,255,255,.08); border: 1px solid rgba(212,175,55,.45); border-radius: 14px; padding: 0.7rem 0.8rem; }
    .mai-features li:nth-child(-n+3) { background: rgba(212,175,55,.16); }
    .mai-features strong { display: block; color: #F5D76E; }
    .mai-features span { display: block; color: rgba(255,255,255,.88); font-size: 0.86rem; margin-top: 0.15rem; }
    .country-list { max-height: 180px; overflow: auto; border-radius: 12px; background: #fff; text-align: left; }
    .country-item { display: block; width: 100%; text-align: left; background: #fff; color: #10233f; border: 0; border-bottom: 1px solid #e8eef6; padding: 0.45rem 0.7rem; cursor: pointer; font-weight: 600; }
    .country-chosen { color: #F5D76E; font-weight: 700; margin: 0.35rem 0; }
    .pay-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .pay-card { display: flex; align-items: center; gap: 0.4rem; background: #fff; color: #10233f; border: 2px solid #d6deea; border-radius: 14px; padding: 0.65rem 0.85rem; font-weight: 700; cursor: pointer; }
    .pay-card:has(input:checked) { border-color: #c6ab47; box-shadow: 0 0 0 3px rgba(198,171,71,.25); }
    .signature-prompt,
    .signature-done {
        text-align: left;
        border-radius: 14px;
        padding: 14px 16px;
    }
    .signature-prompt { background: #fef9c3; border: 2px solid #facc15; color: #713f12; }
    .signature-prompt strong,
    .signature-prompt p { color: #713f12 !important; }
    .signature-done { background: #ecfdf3; border: 2px solid #86efac; color: #14532d; }
    .signature-done strong { color: #14532d !important; }
    .signature-done img {
        display: block;
        max-width: 280px;
        background: #fff;
        border-radius: 8px;
        margin-top: 8px;
    }
    .signature-add {
        background: #fff;
        color: #a16207;
        border: 2px solid #ca8a04;
        border-radius: 10px;
        font-weight: 700;
        padding: 0.45rem 0.8rem;
        cursor: pointer;
    }
    #signature-modal {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 16, 40, .62);
        padding: 16px;
    }
    #signature-modal[hidden] { display: none; }
    .signature-dialog {
        width: min(520px, 100%);
        background: #fff;
        color: #10233f;
        border-radius: 16px;
        padding: 16px;
        text-align: left;
    }
    .signature-dialog h3 { margin: 0 0 8px; color: #10233f; }
    #signature-pad {
        width: 100%;
        height: 160px;
        background: #fff;
        border: 2px dashed #D4AF37;
        border-radius: 12px;
        touch-action: none;
        cursor: crosshair;
    }
</style>
@endpush

@if(($selected ?? '') === 'MESSAGING')
@push('head')
<link rel="manifest" href="{{ asset('whats-mai.webmanifest') }}">
<meta name="theme-color" content="#072f6b">
@endpush
@endif

@section('content')
<section class="subscribe-hero py-10 sm:py-16" style="background-image:url('{{ \App\Support\SiteContent::image('home.hero_image', '/branding/beyond-hero.png') }}');">
    <div class="relative z-10 max-w-5xl mx-auto px-4">
        @if(session('not_permitted'))
            <p class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3">{{ session('not_permitted') }}</p>
        @endif
        @if(empty($onboardingOpen))
            <div class="subscribe-panel rounded-3xl p-6 text-center">
                <h1 class="text-3xl font-bold">Subscribe</h1>
                <p class="mt-3">Company signup is not open yet. The subscriptions page stays available.</p>
            </div>
        @else
        <p class="text-center text-white drop-shadow mb-4">{{ $welcome }}</p>
        @if($errors->any())
            <p class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3">{{ $errors->first() }}</p>
        @endif
        <form method="POST" action="{{ route('cloud.register.submit') }}" id="build-company" class="subscribe-panel rounded-3xl p-5 sm:p-8 text-center" @if(($selected ?? '') === 'MESSAGING') data-mai="1" @endif>
            @csrf
            <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
            <input type="hidden" name="account_kind" id="account-kind" value="{{ old('account_kind', 'company') }}">
            @if(($selected ?? '') === 'MESSAGING')
                <input type="hidden" name="mai" value="1">
            @endif
            @if(!empty($validationToken))
                <input type="hidden" name="validation_token" value="{{ $validationToken }}">
            @endif

            <ol class="stage-rail {{ ($selected ?? '') === 'MESSAGING' ? 'is-track' : '' }}" id="stage-rail">
                @if(($selected ?? '') === 'MESSAGING')
                    <li data-stage="1" class="on">Subscribe</li>
                    <li data-stage="2">Phone</li>
                    <li data-stage="3">Code</li>
                    <li data-stage="4">Name</li>
                    <li data-stage="7">Plan</li>
                @else
                    <li data-stage="1" class="on">Type</li>
                    <li data-stage="2">Phone</li>
                    <li data-stage="3">Code</li>
                    <li data-stage="4">Name</li>
                    <li data-stage="6">Company</li>
                    <li data-stage="7">Services</li>
                @endif
            </ol>

            <section class="step on" data-step="1">
                @if(($selected ?? '') === 'MESSAGING')
                    <img src="{{ asset('branding/mbole-ai.png') }}" alt="" width="92" height="104" class="mx-auto" style="width:92px;height:104px;object-fit:contain">
                    <h1 class="text-3xl sm:text-4xl font-bold drop-shadow">Whats MAI</h1>
                    <p class="hint mt-2">WhatsApp with translation, voice transcription, assisted replies, chat summaries, group messages, and automated posts.</p>
                    @include('cloud.portal.mai_features')
                    <p class="hint mt-2">The code sent to your phone only signs you in. That code does not link WhatsApp. Linking is a separate QR code or link code.</p>
                    <p class="hint mt-2"><a href="{{ route('cloud.login') }}">Already registered? Sign in with your phone code.</a></p>
                @else
                    <h1 class="text-3xl sm:text-4xl font-bold drop-shadow">Subscribe</h1>
                    <p class="hint mt-2">Build your own company. Choose Individual or Company.</p>
                @endif
                <div class="flex flex-col sm:flex-row items-stretch justify-center gap-3 mt-6">
                    <button type="button" id="pick-personal" class="subscribe-choice bg-brand-gold hover:bg-[#b5952f] text-brand-blue px-7 shadow-[0_0_15px_rgba(212,175,55,0.45)]">{{ ($selected ?? '') === 'MESSAGING' ? 'Subscribe' : 'Individual' }}</button>
                    <button type="button" id="pick-company" class="subscribe-choice bg-white/15 hover:bg-white/25 border border-brand-gold text-brand-gold px-7">Company</button>
                </div>
                @if(($selected ?? '') === 'MESSAGING')
                    <p class="hint mt-4">Add Whats MAI to your phone so you can open it without the website. On iPhone, tap Share, then Add to Home Screen.</p>
                    <button type="button" id="install-app" class="mt-3 bg-white/15 border border-brand-gold text-brand-gold font-bold rounded-full px-5 py-2.5" hidden>Add Whats MAI to this phone</button>
                @endif
            </section>

            <section class="step" data-step="2">
                <button type="button" class="back-step" data-back="1">Back</button>
                <h2 class="text-xl font-bold">Phone number</h2>
                <p class="hint mt-1">A verification code is sent on WhatsApp before any service is shown. That code only signs you in. It does not connect WhatsApp.</p>
                <div class="field-row">
                    <label for="{{ ($selected ?? '') === 'MESSAGING' ? 'country-search' : 'dial' }}">Country</label>
                    @if(($selected ?? '') === 'MESSAGING')
                        <input class="px-4 py-2" id="country-search" placeholder="Search a country" autocomplete="off">
                        <input type="hidden" name="dial" id="dial" value="{{ old('dial', '237') }}">
                        <p class="country-chosen" id="country-chosen">Cameroon (+237)</p>
                        <div id="country-list" class="country-list">
                            @foreach(\App\Support\CountryDialCodes::all() as $label)
                                @php $dialCode = preg_replace('/\D+/', '', $label); @endphp
                                <button type="button" class="country-item" data-dial="{{ $dialCode }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    @else
                        <select name="dial" id="dial">
                            <option value="237" {{ old('dial', '237') === '237' ? 'selected' : '' }}>+237 Cameroon</option>
                            <option value="250" {{ old('dial') === '250' ? 'selected' : '' }}>+250 Rwanda</option>
                        </select>
                    @endif
                </div>
                <div class="field-row">
                    <label for="phone">Phone number</label>
                    <input class="px-4 py-2" name="phone" id="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel">
                </div>
                <button type="button" id="send-code" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Send WhatsApp code</button>
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="send-error" hidden></p>
            </section>

            <section class="step" data-step="3">
                <button type="button" class="back-step" data-back="2">Back</button>
                <h2 class="text-xl font-bold">Verification code</h2>
                <p class="hint mt-1">Enter the code sent to this phone on WhatsApp.</p>
                <div class="field-row">
                    <label for="otp-code">Code</label>
                    <input class="px-4 py-2" id="otp-code" inputmode="numeric" autocomplete="one-time-code">
                </div>
                <button type="button" id="check-code" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Verify and continue</button>
                <p class="hint mt-3" id="resend-wait">You can resend the code in 2:00</p>
                <button type="button" id="resend-code" class="mt-2 bg-white/15 border border-brand-gold text-brand-gold font-bold rounded-full px-5 py-2.5" disabled>Resend code</button>
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="code-error" hidden></p>
            </section>

            <section class="step" data-step="4">
                <button type="button" class="back-step" data-back="3">Back</button>
                <h2 class="text-xl font-bold" id="name-title">Your name</h2>
                <p class="hint mt-2" id="name-note"></p>
                <div class="field-row">
                    <label for="full-name">Name</label>
                    <input class="px-4 py-2" name="full_name" id="full-name" value="{{ old('full_name', trim(old('first_name').' '.old('last_name'))) }}" autocomplete="name">
                </div>
                <input type="hidden" name="first_name" id="first-name" value="{{ old('first_name') }}">
                <input type="hidden" name="last_name" id="last-name" value="{{ old('last_name') }}">
                <button type="button" id="after-name" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Continue</button>
            </section>

            <section class="step" data-step="6">
                <button type="button" class="back-step" data-back="4">Back</button>
                <h2 class="text-xl font-bold">Company</h2>
                <p class="hint mt-1">The name and phone already entered are used for this company.</p>
                <p class="known-name" id="company-known"></p>
                <input type="hidden" name="company_name" id="company-name" value="{{ old('company_name') }}">
                <input type="hidden" name="company_phone" id="company-phone" value="{{ old('company_phone') }}">
                <input type="hidden" name="company_email" id="company-email" value="{{ old('company_email') }}">
                <div class="field-row">
                    <label for="country">Country</label>
                    <input class="px-4 py-2" name="country" id="country" value="{{ old('country') }}" maxlength="64">
                </div>
                <div class="field-row">
                    <label for="city">City</label>
                    <input class="px-4 py-2" name="city" id="city" value="{{ old('city') }}">
                </div>
                <div class="field-row">
                    <label for="address">Address</label>
                    <input class="px-4 py-2" name="address" id="address" value="{{ old('address') }}">
                </div>
                <div class="field-row">
                    <label for="timezone">Timezone</label>
                    <select class="px-4 py-2" name="timezone" id="timezone">
                        @foreach(['Africa/Douala', 'Africa/Lagos', 'UTC'] as $zone)
                            <option value="{{ $zone }}" {{ old('timezone', 'Africa/Douala') === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-row">
                    <label for="currency">Currency</label>
                    <select class="px-4 py-2" name="currency" id="currency">
                        @foreach(['XAF', 'USD', 'EUR', 'NGN', 'GHS'] as $code)
                            <option value="{{ $code }}" {{ old('currency', 'XAF') === $code ? 'selected' : '' }}>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" id="to-services" class="mt-4 bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Continue to services</button>
            </section>

            <section class="step" data-step="7">
                <button type="button" class="back-step" data-back="services">Back</button>
                <h2 class="text-xl font-bold">Services</h2>
                <p class="hint mt-1">Select one or more. The total updates as you tick them.</p>
                <div class="service-grid">
                @foreach($plans as $plan)
                    @if(($selected ?? '') !== '' && ($selected ?? '') !== $plan->module->code)
                        @continue
                    @endif
                    <label class="service-card" data-service="{{ $plan->module->code }}">
                        <input type="checkbox" name="modules[]" value="{{ $plan->module->code }}" data-price="{{ $plan->price }}" data-name="{{ $plan->name }}" {{ old('modules') ? (in_array($plan->module->code, (array) old('modules'), true) ? 'checked' : '') : (($selected ?? '') === $plan->module->code ? 'checked' : '') }}>
                        <span>
                            <strong>{{ $plan->name }}</strong>
                            <span class="block text-sm">{{ number_format((float) $plan->price, 0) }} {{ $plan->currency }} per {{ strtolower($plan->billing_interval ?: 'month') }}</span>
                            <span class="block text-sm">Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</span>
                            <span class="block text-sm">{{ $plan->module->description }}</span>
                        </span>
                    </label>
                @endforeach
                </div>
                <div class="service-grid">
                <div class="rounded-2xl border border-gray-200 p-4" id="price-summary">
                    <strong>Selected modules</strong>
                    <div id="price-lines"></div>
                    <div class="flex justify-between mt-2"><span>Monthly total</span><span id="price-total">0</span></div>
                    <div class="flex justify-between"><span>Free trial</span><span>{{ $quote['trial'] }}</span></div>
                    <p class="text-sm mt-2">Pay now with MTN, Orange, VISA, or crypto. The free trial does not charge you.</p>
                </div>
                @if(($selected ?? '') !== 'MESSAGING')
                <div>
                    <h3 class="text-left font-semibold mb-2">Digital Signature</h3>
                    <div id="signature-prompt" class="signature-prompt">
                        <strong>Signature Required</strong>
                        <p class="text-sm mt-1 mb-3">A digital signature is required to finish this subscription.</p>
                        <button type="button" id="open-signature" class="signature-add">Add Signature</button>
                    </div>
                    <div id="signature-done" class="signature-done" hidden>
                        <strong>Signature captured</strong>
                        <img id="signature-preview" alt="Your signature">
                        <button type="button" id="resign-signature" class="signature-add mt-3">Re-sign</button>
                    </div>
                </div>
                @endif
                </div>
                <input type="hidden" name="signature" id="signature" value="">
                @if(($selected ?? '') !== 'MESSAGING')
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="signature-error" hidden>Add your signature before continuing.</p>
                @endif
                <fieldset class="mt-4 text-left">
                    <legend class="font-semibold mb-2">Payment method, if you pay now</legend>
                    <div class="{{ ($selected ?? '') === 'MESSAGING' ? 'pay-grid' : 'flex flex-wrap gap-2' }}">
                        @foreach(['MTN' => 'MTN MoMo', 'ORANGE' => 'Orange Money', 'VISA' => 'VISA', 'CRYPTO' => 'Crypto'] as $code => $label)
                            <label class="{{ ($selected ?? '') === 'MESSAGING' ? 'pay-card' : 'rounded-full border border-brand-gold px-4 py-2 font-bold' }} {{ in_array($code, ['MTN', 'ORANGE'], true) ? 'pay-local' : '' }}">
                                <input type="radio" name="payment_method" value="{{ $code }}" {{ old('payment_method') === $code ? 'checked' : '' }}>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @if(($selected ?? '') === 'MESSAGING')
                        <p class="hint mt-2">Cameroon can pay with MTN or Orange. Every country can pay by card or crypto.</p>
                    @endif
                </fieldset>
                @if(($selected ?? '') === 'MESSAGING')
                    @include('cloud.portal.mai_features')
                @endif
                <p class="mt-3 rounded-xl bg-red-50 text-red-800 px-3 py-2" id="pay-method-error" hidden>Choose MTN, Orange, VISA, or crypto before paying.</p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-4">
                    <button type="submit" name="start_mode" value="trial" class="bg-brand-gold text-brand-blue font-bold rounded-full px-5 py-2.5">Start free trial</button>
                    <button type="submit" name="start_mode" value="pay" id="pay-now" class="bg-white/15 border border-brand-gold text-brand-gold font-bold rounded-full px-5 py-2.5">Pay</button>
                </div>
            </section>
        </form>
        @if(($selected ?? '') !== 'MESSAGING')
        <div id="signature-modal" hidden>
            <div class="signature-dialog" role="dialog" aria-labelledby="signature-title">
                <h3 id="signature-title">Add your signature</h3>
                <canvas id="signature-pad" width="640" height="180"></canvas>
                <div class="flex flex-wrap gap-2 mt-3">
                    <button type="button" id="use-signature" class="bg-brand-gold text-brand-blue font-bold rounded-full px-4 py-2">Use this signature</button>
                    <button type="button" id="clear-signature" class="underline text-sm">Clear</button>
                    <button type="button" id="close-signature" class="underline text-sm">Close</button>
                </div>
            </div>
        </div>
        @endif
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('build-company');
        if (!form) return;
        var token = form.querySelector('input[name="_token"]').value;
        var kind = document.getElementById('account-kind');
        var lines = document.getElementById('price-lines');
        var total = document.getElementById('price-total');
        function show(step) {
            var nodes = form.querySelectorAll('.step');
            var current = String(step);
            for (var i = 0; i < nodes.length; i++) {
                nodes[i].className = nodes[i].getAttribute('data-step') === current ? 'step on' : 'step';
            }
            var stages = document.querySelectorAll('#stage-rail li');
            for (var s = 0; s < stages.length; s++) {
                var n = stages[s].getAttribute('data-stage');
                var cls = '';
                if (n === '6' && kind && kind.value === 'personal') cls = 'skip';
                else if (n === current) cls = 'on';
                else if (parseInt(n, 10) < parseInt(current, 10)) cls = 'done';
                stages[s].className = cls;
            }
        }
        function paint() {
            var sum = 0;
            var html = '';
            var boxes = form.querySelectorAll('input[name="modules[]"]');
            for (var i = 0; i < boxes.length; i++) {
                if (!boxes[i].checked) continue;
                var price = parseFloat(boxes[i].getAttribute('data-price') || '0');
                sum += price;
                html += '<div class="flex justify-between"><span>' + boxes[i].getAttribute('data-name') + '</span><span>' + price + '</span></div>';
            }
            lines.innerHTML = html;
            total.textContent = sum + ' XAF';
        }
        function post(url, body, done) {
            var data = new FormData();
            data.append('_token', token);
            Object.keys(body).forEach(function (key) { data.append(key, body[key]); });
            fetch(url, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json().then(function (json) { done(response.ok, json); }); })
                .catch(function () { done(false, { message: 'Try again.' }); });
        }
        function splitName(value) {
            var name = (value || '').replace(/\s+/g, ' ').trim();
            if (!name) return ['', ''];
            var parts = name.split(' ');
            var first = parts.shift();
            var last = parts.join(' ').trim();
            return [first, last || first];
        }
        function writeName() {
            var parts = splitName(document.getElementById('full-name').value);
            document.getElementById('first-name').value = parts[0];
            document.getElementById('last-name').value = parts[1];
            return (parts[0] + ' ' + parts[1]).trim();
        }
        function personName() {
            return writeName();
        }
        var resendTimer = null;
        function startResendWait() {
            var left = 120;
            var button = document.getElementById('resend-code');
            var label = document.getElementById('resend-wait');
            if (resendTimer) clearInterval(resendTimer);
            function paintWait() {
                var minutes = Math.floor(left / 60);
                var seconds = left % 60;
                label.textContent = left > 0
                    ? 'You can resend the code in ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds
                    : 'You can resend the code.';
                button.disabled = left > 0;
            }
            paintWait();
            resendTimer = setInterval(function () {
                left -= 1;
                if (left <= 0) {
                    left = 0;
                    clearInterval(resendTimer);
                }
                paintWait();
            }, 1000);
        }
        function mbolePhone() {
            var dial = document.getElementById('dial');
            var field = document.getElementById('phone');
            var code = dial ? String(dial.value || '237').replace(/\D+/g, '') : '237';
            var digits = (field && field.value ? field.value : '').replace(/\D+/g, '');
            if (code && digits.indexOf(code) === 0 && digits.length >= code.length + 7) return '+' + digits;
            digits = digits.replace(/^0+/, '');
            return '+' + code + digits;
        }
        function paintPay() {
            var dial = document.getElementById('dial');
            var local = dial && String(dial.value) === '237';
            var cards = document.querySelectorAll('.pay-local');
            for (var i = 0; i < cards.length; i++) cards[i].hidden = !local;
        }
        function sendCode(errorId) {
            var error = document.getElementById(errorId);
            error.hidden = true;
            post('{{ route('cloud.register.otp') }}', {
                phone: mbolePhone(),
                account_kind: kind.value
            }, function (ok, json) {
                if (!ok) {
                    error.hidden = false;
                    error.textContent = json.message || 'The code could not be sent.';
                    return;
                }
                show(3);
                startResendWait();
            });
        }
        function applyKnownCompany() {
            var full = personName();
            document.getElementById('company-name').value = full;
            document.getElementById('company-phone').value = mbolePhone();
            document.getElementById('company-email').value = '';
            document.getElementById('company-known').textContent = full;
            return full;
        }
        document.getElementById('pick-personal').onclick = function () {
            kind.value = 'personal';
            document.getElementById('name-title').textContent = 'Your name';
            show(2);
        };
        document.getElementById('pick-company').onclick = function () {
            kind.value = 'company';
            document.getElementById('name-title').textContent = 'Contact person';
            show(2);
        };
        document.getElementById('send-code').onclick = function () { sendCode('send-error'); };
        document.getElementById('resend-code').onclick = function () { sendCode('code-error'); };
        document.getElementById('check-code').onclick = function () {
            var error = document.getElementById('code-error');
            error.hidden = true;
            post('{{ route('cloud.register.otp.verify') }}', {
                phone: mbolePhone(),
                dial: document.getElementById('dial') ? document.getElementById('dial').value : '237',
                code: document.getElementById('otp-code').value,
                mai: form.getAttribute('data-mai') === '1' ? '1' : ''
            }, function (ok, json) {
                if (!ok) {
                    error.hidden = false;
                    error.textContent = json.message || 'That code does not match.';
                    return;
                }
                post('{{ route('cloud.register.identity') }}', {
                    phone: mbolePhone(),
                    account_kind: kind.value
                }, function (named, identity) {
                    document.getElementById('full-name').value = named ? (identity.name || '') : '';
                    writeName();
                    var note = document.getElementById('name-note');
                    if (named && identity.source === 'campay') {
                        note.textContent = kind.value === 'company'
                            ? 'Campay returned this name for the contact person. Change it if it is not correct.'
                            : 'Campay returned this name for the phone number. Change it if it is not you.';
                    } else if (named && identity.source === 'whatsapp') {
                        note.textContent = 'This name is the one saved on WhatsApp for this number. Change it if it is not you.';
                    } else {
                        note.textContent = 'Campay and WhatsApp did not return a name. Enter it.';
                    }
                    if (form.getAttribute('data-mai') === '1') {
                        note.textContent = (note.textContent ? note.textContent + ' ' : '') + 'The app link was sent to this WhatsApp. Open it on your phone to add Whats MAI.';
                    }
                    show(4);
                });
            });
        };
        document.getElementById('after-name').onclick = function () {
            writeName();
            applyKnownCompany();
            if (kind.value === 'personal') {
                show(7);
                return;
            }
            show(6);
        };
        document.getElementById('to-services').onclick = function () { show(7); };
        var ink = false;
        var savedUrl = '';
        var pad = document.getElementById('signature-pad');
        var modal = document.getElementById('signature-modal');
        var padCtx = pad ? pad.getContext('2d') : null;
        if (pad && padCtx) {
        padCtx.lineWidth = 2.2;
        padCtx.lineCap = 'round';
        padCtx.strokeStyle = '#0b3f90';
        function padPoint(event) {
            var rect = pad.getBoundingClientRect();
            var source = event.touches ? event.touches[0] : event;
            return {
                x: (source.clientX - rect.left) * (pad.width / rect.width),
                y: (source.clientY - rect.top) * (pad.height / rect.height)
            };
        }
        function drawTo(event) {
            if (!pad.drawing) return;
            event.preventDefault();
            var point = padPoint(event);
            padCtx.lineTo(point.x, point.y);
            padCtx.stroke();
            ink = true;
        }
        function openPad() { modal.hidden = false; }
        pad.addEventListener('mousedown', function (event) {
            pad.drawing = true;
            var point = padPoint(event);
            padCtx.beginPath();
            padCtx.moveTo(point.x, point.y);
        });
        pad.addEventListener('mousemove', drawTo);
        pad.addEventListener('mouseup', function () { pad.drawing = false; });
        pad.addEventListener('mouseleave', function () { pad.drawing = false; });
        pad.addEventListener('touchstart', function (event) {
            pad.drawing = true;
            var point = padPoint(event);
            padCtx.beginPath();
            padCtx.moveTo(point.x, point.y);
        }, { passive: false });
        pad.addEventListener('touchmove', drawTo, { passive: false });
        pad.addEventListener('touchend', function () { pad.drawing = false; });
        document.getElementById('open-signature').onclick = openPad;
        document.getElementById('resign-signature').onclick = openPad;
        document.getElementById('close-signature').onclick = function () { modal.hidden = true; };
        document.getElementById('clear-signature').onclick = function () {
            padCtx.clearRect(0, 0, pad.width, pad.height);
            ink = false;
        };
        document.getElementById('use-signature').onclick = function () {
            if (!ink) return;
            savedUrl = pad.toDataURL('image/png');
            document.getElementById('signature').value = savedUrl;
            document.getElementById('signature-preview').src = savedUrl;
            document.getElementById('signature-prompt').hidden = true;
            document.getElementById('signature-done').hidden = false;
            document.getElementById('signature-error').hidden = true;
            modal.hidden = true;
        };
        }
        var backs = form.querySelectorAll('.back-step');
        for (var b = 0; b < backs.length; b++) {
            backs[b].onclick = function () {
                var target = this.getAttribute('data-back');
                if (target === 'services') {
                    show(kind.value === 'personal' ? 4 : 6);
                    return;
                }
                show(target);
            };
        }
        form.addEventListener('change', paint);
        form.addEventListener('submit', function (event) {
            applyKnownCompany();
            if (form.getAttribute('data-mai') === '1') return;
            var error = document.getElementById('signature-error');
            if (!error) return;
            if (!savedUrl) {
                event.preventDefault();
                error.hidden = false;
                return;
            }
            document.getElementById('signature').value = savedUrl;
        });
        var search = document.getElementById('country-search');
        if (search) {
            var items = document.querySelectorAll('.country-item');
            search.oninput = function () {
                var query = search.value.toLowerCase();
                for (var i = 0; i < items.length; i++) {
                    items[i].hidden = items[i].textContent.toLowerCase().indexOf(query) === -1;
                }
            };
            for (var c = 0; c < items.length; c++) {
                items[c].onclick = function () {
                    document.getElementById('dial').value = this.getAttribute('data-dial');
                    document.getElementById('country-chosen').textContent = this.textContent;
                    search.value = '';
                    paintPay();
                };
            }
        }
        paintPay();
        paint();
        @php
            $resume = 1;
            if ($errors->any()) {
                $resume = 4;
                if ($errors->has('modules') || $errors->has('signature')) {
                    $resume = 7;
                } elseif ($errors->has('country') || $errors->has('city') || $errors->has('address') || $errors->has('timezone') || $errors->has('currency')) {
                    $resume = 6;
                } elseif ($errors->has('first_name') || $errors->has('last_name') || $errors->has('full_name')) {
                    $resume = 4;
                } elseif ($errors->has('phone') && ! $errors->has('password') && ! $errors->has('email') && ! $errors->has('username')) {
                    $resume = 2;
                }
            } elseif (session('not_permitted') && strpos((string) session('not_permitted'), 'Verify the phone') !== false) {
                $resume = 3;
            } elseif (session('not_permitted') && (strpos((string) session('not_permitted'), 'username') !== false || strpos((string) session('not_permitted'), 'email') !== false)) {
                $resume = 5;
            } elseif (session('not_permitted') && strpos((string) session('not_permitted'), 'crypto') !== false) {
                $resume = 7;
            }
        @endphp
        @if($resume > 1)
        if (kind) kind.value = '{{ old('account_kind', 'company') }}';
        show({{ $resume }});
        @endif
        var payNow = document.getElementById('pay-now');
        if (payNow) {
            payNow.addEventListener('click', function (event) {
                var picked = document.querySelector('input[name="payment_method"]:checked');
                var note = document.getElementById('pay-method-error');
                if (picked) {
                    if (note) note.hidden = true;
                    return;
                }
                event.preventDefault();
                if (note) note.hidden = false;
            });
        }
        if (form && form.getAttribute('data-mai') === '1' && 'serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ url('/whatsapp-mai/sw.js') }}', { scope: '/whatsapp-mai/' }).catch(function () {});
            window.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                var button = document.getElementById('install-app');
                if (!button) return;
                button.hidden = false;
                button.onclick = function () { event.prompt(); };
            });
        }
    })();
</script>
@endpush

@extends('beyond.layout')

@section('title', 'Apply for Permission')
@section('meta_description', 'Request time-off permission from Beyond Enterprise.')

@php
    $startDetails = (bool) ($verifyStep
        || old('existing_user_id')
        || ($draft['existing_user_id'] ?? null)
        || ($user && $user->phone));
@endphp

@section('content')
@include('beyond.apply.partials.apply_styles')
<style>
    .perm-kicker { letter-spacing: .16em; font-size: .65rem; font-weight: 800; text-transform: uppercase; color: #d4af37; }
    .perm-label { display: flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 700; color: #0b3f90; }
    .perm-hint { font-size: .72rem; color: #64748b; margin-top: .3rem; line-height: 1.35; }
    .perm-step { display: flex; gap: .75rem; align-items: flex-start; padding: .85rem 0; }
    .perm-step-n {
        flex-shrink: 0; width: 1.85rem; height: 1.85rem; border-radius: 999px;
        background: rgba(212,175,55,.15); color: #c6ab47; font-weight: 800; font-size: .75rem;
        display: flex; align-items: center; justify-content: center; border: 1px solid rgba(212,175,55,.35);
    }
    .perm-section {
        border: 1px solid #eef2f7; border-radius: 1rem; padding: 1rem 1rem 1.05rem;
        background: linear-gradient(180deg, #fbfcfe 0%, #fff 40%);
    }
    .perm-section + .perm-section { margin-top: .85rem; }
    .perm-section h3 { margin: 0 0 .75rem; font-size: .88rem; font-weight: 800; color: #0b3f90; display: flex; align-items: center; gap: .45rem; }
    .perm-otp {
        border: 1.5px solid rgba(212,175,55,.45); background: linear-gradient(180deg, #fffbeb, #fff);
        border-radius: 1.15rem; padding: 1.1rem 1.15rem; box-shadow: 0 12px 32px rgba(198,171,71,.12);
    }
    .perm-form-card { border-top: 4px solid #d4af37; }
    .perm-status { display: flex; align-items: flex-start; gap: .55rem; margin-top: .75rem; border-radius: .9rem; padding: .7rem .85rem; font-size: .82rem; font-weight: 600; line-height: 1.35; }
    .perm-name-card {
        display: flex; align-items: center; gap: .75rem;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 1rem; padding: .85rem 1rem;
    }
    .perm-cc-btn {
        margin-top: 0; min-width: 4.6rem; width: 4.6rem; padding-left: .55rem; padding-right: 1.35rem;
        font-size: .92rem; font-weight: 800; color: #0b3f90; text-align: left; position: relative;
        background-image: linear-gradient(45deg, transparent 50%, #0b3f90 50%), linear-gradient(135deg, #0b3f90 50%, transparent 50%);
        background-position: calc(100% - 12px) calc(50% - 3px), calc(100% - 7px) calc(50% - 3px);
        background-size: 5px 5px, 5px 5px; background-repeat: no-repeat;
    }
    .perm-sheet-bg { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 70; }
    .perm-sheet {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 71;
        background: #fff; border-radius: 1.25rem 1.25rem 0 0;
        max-height: min(78vh, 560px); display: flex; flex-direction: column;
        box-shadow: 0 -12px 40px rgba(15,23,42,.18);
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    .perm-sheet-handle { width: 2.4rem; height: .28rem; border-radius: 999px; background: #cbd5e1; margin: .55rem auto .15rem; }
    .perm-inline-submit { margin-top: 1.1rem; }
    .perm-sticky { display: none; }
    .perm-sticky.perm-sticky-hide { display: none !important; }
    @media (max-width: 640px) {
        .perm-hero { padding-top: 1.15rem !important; padding-bottom: 1.35rem !important; }
        .perm-hero h1 { font-size: 1.55rem !important; line-height: 1.15; }
        .perm-hero p { font-size: .9rem !important; }
        .perm-wrap { margin-top: -.65rem; padding-left: .75rem; padding-right: .75rem; }
        .perm-form-card { padding: 1rem .9rem 1.15rem !important; border-radius: 1rem; }
        .perm-section { padding: .9rem .85rem .95rem; }
        .perm-inline-submit { display: none !important; }
        .perm-sticky {
            display: block;
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 45;
            padding: .7rem 4.75rem .7rem 1rem;
            padding-bottom: calc(.7rem + env(safe-area-inset-bottom, 0px));
            background: rgba(255,255,255,.96);
            border-top: 1px solid rgba(15,23,42,.08);
            box-shadow: 0 -8px 24px rgba(15,23,42,.08);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        }
        .perm-page { padding-bottom: calc(6.75rem + env(safe-area-inset-bottom, 0px)); }
        input.apply-field, textarea.apply-field, button.apply-field { font-size: 16px; min-height: 3rem; }
        input[type="datetime-local"].apply-field { min-height: 3.1rem; }
    }
    @media (min-width: 641px) {
        .perm-cc-btn { min-width: 13rem; width: 13rem; }
        .perm-sheet-bg { display: none !important; }
        .perm-sheet {
            position: absolute; left: 0; right: auto; bottom: auto; top: 100%; margin-top: .35rem;
            width: 20rem; max-height: 18rem; border-radius: .9rem; z-index: 40;
            box-shadow: 0 12px 32px rgba(15,23,42,.14); padding-bottom: 0;
        }
        .perm-sheet-handle { display: none; }
    }
</style>

<div class="min-h-screen apply-shell perm-page"
     x-data="permissionApply()"
     x-init="boot()">
    <div class="relative overflow-hidden bg-brand-blue text-white perm-hero">
        <div class="absolute inset-0 opacity-30" style="background:linear-gradient(120deg,rgba(212,175,55,.38),transparent 48%),radial-gradient(circle at 85% 15%,rgba(255,255,255,.14),transparent 42%);"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-10 md:py-14">
            <p class="perm-kicker m-0">Existing accounts</p>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight mt-2 mb-2">Apply for Permission</h1>
            <p class="text-blue-100 text-base md:text-lg max-w-2xl m-0 leading-relaxed">
                Start with your WhatsApp number. We load your name from the system — then you add role, subject, and reason.
            </p>
            <div class="flex gap-2 mt-3">
                <span class="text-[11px] font-bold rounded-full px-2.5 py-1 border"
                      :class="step === 'phone' ? 'bg-brand-gold text-brand-dark border-brand-gold' : 'bg-white/10 border-white/15'">1 · WhatsApp</span>
                <span class="text-[11px] font-bold rounded-full px-2.5 py-1 border"
                      :class="step === 'details' ? 'bg-brand-gold text-brand-dark border-brand-gold' : 'bg-white/10 border-white/15'">2 · Request</span>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 -mt-8 relative z-10 perm-wrap">
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <aside class="hidden lg:block lg:col-span-4 apply-panel p-6 md:p-7">
                <h2 class="text-lg font-extrabold text-brand-blue m-0 mb-1">How it works</h2>
                <p class="text-sm text-slate-500 m-0 mb-4">Staff and customers both qualify. Pick any country code.</p>
                <div class="divide-y divide-slate-100">
                    <div class="perm-step">
                        <span class="perm-step-n">1</span>
                        <div>
                            <p class="font-bold text-slate-800 m-0">WhatsApp</p>
                            <p class="text-sm text-slate-500 m-0 mt-0.5">Choose your country, enter the number on your account.</p>
                        </div>
                    </div>
                    <div class="perm-step">
                        <span class="perm-step-n">2</span>
                        <div>
                            <p class="font-bold text-slate-800 m-0">We find your name</p>
                            <p class="text-sm text-slate-500 m-0 mt-0.5">No need to type it. Then add role, subject, and reason.</p>
                        </div>
                    </div>
                    <div class="perm-step">
                        <span class="perm-step-n">3</span>
                        <div>
                            <p class="font-bold text-slate-800 m-0">Signed letter</p>
                            <p class="text-sm text-slate-500 m-0 mt-0.5">After review you receive the letter on WhatsApp.</p>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="lg:col-span-8 apply-panel perm-form-card p-5 sm:p-7 md:p-8">
                @if(session('success'))
                    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 text-sm font-medium">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-3.5 py-2.5 text-sm">
                        <ul class="list-disc pl-5 m-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                @if($verifyStep)
                    <div class="perm-otp mb-5">
                        <p class="perm-kicker m-0 mb-2">Almost done</p>
                        <h2 class="text-xl font-extrabold text-brand-blue m-0 mb-1">Enter WhatsApp code</h2>
                        <p class="text-sm text-slate-600 m-0 mb-4">Sent to <strong>{{ $maskedPhone }}</strong></p>
                        <form method="POST" action="{{ route('beyond.permissions.verify') }}" class="space-y-3">
                            @csrf
                            <input type="text" name="otp" maxlength="6" required inputmode="numeric"
                                   autocomplete="one-time-code" enterkeyhint="done"
                                   class="apply-field text-center text-2xl tracking-[0.35em] font-extrabold"
                                   placeholder="••••••">
                            <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3.5 rounded-xl inline-flex items-center justify-center gap-2 min-h-[3.1rem]">
                                <i data-lucide="shield-check" class="w-5 h-5"></i> Verify &amp; submit
                            </button>
                        </form>
                        <form method="POST" action="{{ route('beyond.permissions.resend') }}" class="mt-3 text-center">
                            @csrf
                            <button type="submit" class="text-sm text-brand-light font-semibold hover:underline min-h-[2.5rem]">Resend code</button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('beyond.permissions.store') }}" id="perm-form"
                      @submit="if (step !== 'details' || !accountFound) $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="existing_user_id" x-model="existingUserId">
                    <input type="hidden" name="country_code" :value="countryCode">
                    <input type="hidden" name="phone" :value="phoneLocal">

                    <div x-show="step === 'phone'" x-cloak>
                        <div class="perm-section">
                            <h3><i data-lucide="smartphone" class="w-4 h-4 text-brand-gold"></i> Your WhatsApp number</h3>
                            <p class="text-sm text-slate-500 m-0 mb-3">Pick any country, then enter the number on your account.</p>
                            <label class="perm-label" for="perm-phone">WhatsApp number *</label>
                            <div class="apply-phone-row">
                                <div class="relative shrink-0" @click.away="ccOpen = false" @keydown.escape.window="ccOpen = false">
                                    <button type="button"
                                            class="apply-field perm-cc-btn"
                                            @click="ccOpen = !ccOpen; $nextTick(() => { if (ccOpen && $refs.ccSearch) $refs.ccSearch.focus(); })"
                                            :aria-expanded="ccOpen ? 'true' : 'false'">
                                        <span class="hidden sm:inline truncate" x-text="ccLabel()"></span>
                                        <span class="sm:hidden" x-text="countryCode"></span>
                                    </button>
                                    <div x-show="ccOpen" x-cloak>
                                        <div class="perm-sheet-bg sm:hidden" @click="ccOpen = false"></div>
                                        <div class="perm-sheet">
                                            <div class="perm-sheet-handle"></div>
                                            <div class="flex items-center justify-between px-4 pt-1 pb-2">
                                                <p class="m-0 text-sm font-extrabold text-brand-blue">Country code</p>
                                                <button type="button" class="text-sm font-semibold text-slate-500 sm:hidden" @click="ccOpen = false">Done</button>
                                            </div>
                                            <input type="search" x-model="ccQuery" x-ref="ccSearch"
                                                   @click.stop placeholder="Search country or +code"
                                                   enterkeyhint="search" inputmode="search"
                                                   class="mx-3 mb-2 rounded-xl border border-slate-200 px-3 py-2.5 text-base outline-none">
                                            <div class="overflow-auto flex-1 px-1 pb-2">
                                                <template x-for="c in filteredCountries()" :key="c.code">
                                                    <button type="button" @click="selectCountry(c)"
                                                            class="w-full text-left px-3 py-3 text-sm rounded-lg min-h-[2.75rem]"
                                                            :class="c.code === countryCode ? 'bg-blue-50 font-semibold text-brand-blue' : 'hover:bg-slate-50'"
                                                            x-text="c.label"></button>
                                                </template>
                                                <p class="px-3 py-2 text-xs text-gray-500" x-show="filteredCountries().length === 0">No matches.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input id="perm-phone" x-model="phoneLocal"
                                       data-wa-phone="off" class="apply-field apply-phone-local wa-phone-off"
                                       @input="onPhoneInput()"
                                       @keydown.enter.prevent="accountFound ? goDetails() : lookupPhone()"
                                       type="text" inputmode="numeric" autocomplete="off"
                                       enterkeyhint="search"
                                       placeholder="National number">
                            </div>
                            <p class="perm-hint">We’ll look up your name. Do not type a country code in this box.</p>
                        </div>

                        <div x-show="lookingUp" x-cloak class="perm-status bg-slate-50 border border-slate-200 text-slate-600">
                            <i data-lucide="loader" class="w-4 h-4 mt-0.5 shrink-0 animate-spin"></i>
                            <p class="m-0">Checking this number…</p>
                        </div>
                        <div x-show="!lookingUp && lookupMessage && !accountFound" x-cloak class="perm-status bg-red-50 border border-red-200 text-red-800">
                            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
                            <p class="m-0" x-text="lookupMessage"></p>
                        </div>

                        <button type="button"
                                class="perm-inline-submit w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl inline-flex items-center justify-center gap-2 disabled:opacity-50 min-h-[3.1rem]"
                                :disabled="!accountFound"
                                @click="goDetails()">
                            Continue
                        </button>
                        <p class="text-center text-[13px] text-slate-500 mt-3 mb-0" x-show="!accountFound">Enter a WhatsApp number on an existing account.</p>
                    </div>

                    <div x-show="step === 'details'" x-cloak>
                        <div class="perm-name-card mb-4">
                            <div class="h-11 w-11 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center shrink-0">
                                <i data-lucide="check" class="w-5 h-5 text-emerald-600"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="m-0 text-xs font-bold uppercase tracking-wide text-slate-500">Account</p>
                                <p class="m-0 font-extrabold text-brand-blue truncate" x-text="fullName || 'Account found'"></p>
                                <p class="m-0 text-xs text-slate-500" x-text="displayNumber()"></p>
                            </div>
                            <button type="button" class="ml-auto text-sm font-bold text-brand-blue shrink-0" @click="step = 'phone'">Change</button>
                        </div>

                        <div class="perm-section">
                            <h3><i data-lucide="clipboard-list" class="w-4 h-4 text-brand-gold"></i> Your request</h3>
                            <div class="space-y-3.5">
                                <div>
                                    <label class="perm-label" for="perm-role">Your role *</label>
                                    <input id="perm-role" required name="company_role" x-model="companyRole"
                                           class="apply-field" enterkeyhint="next" autocomplete="organization-title"
                                           placeholder="e.g. Personal assistant, Technician">
                                </div>
                                <div>
                                    <label class="perm-label" for="perm-subject">Subject *</label>
                                    <input id="perm-subject" required name="subject" maxlength="255"
                                           value="{{ old('subject', $draft['subject'] ?? '') }}"
                                           class="apply-field" enterkeyhint="next"
                                           placeholder="e.g. Permission to go home">
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3.5">
                                    <div>
                                        <label class="perm-label" for="perm-from">From *</label>
                                        <input id="perm-from" required type="datetime-local" name="from_at"
                                               value="{{ old('from_at', $draft['from_at'] ?? '') }}"
                                               class="apply-field">
                                    </div>
                                    <div>
                                        <label class="perm-label" for="perm-to">To *</label>
                                        <input id="perm-to" required type="datetime-local" name="to_at"
                                               value="{{ old('to_at', $draft['to_at'] ?? '') }}"
                                               class="apply-field">
                                    </div>
                                </div>
                                <div>
                                    <label class="perm-label" for="perm-reason">Reason *</label>
                                    <textarea id="perm-reason" required name="reason" rows="3"
                                              class="apply-field"
                                              placeholder="Why do you need this permission?">{{ old('reason', $draft['reason'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="perm-inline-submit w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl inline-flex items-center justify-center gap-2 disabled:opacity-50 min-h-[3.1rem]"
                                :disabled="!accountFound">
                            {{ $otpOk ? 'Submit request' : 'Send WhatsApp code' }}
                        </button>
                    </div>

                    <p class="text-center text-sm text-slate-500 mt-4 mb-0">
                        Have a login?
                        <a href="{{ url('/login?redirect=/permissions') }}" class="text-brand-blue font-bold hover:underline">Sign in</a>
                        ·
                        <a href="{{ url('/forgot-password') }}" class="text-brand-blue font-bold hover:underline">Reset password</a>
                    </p>
                </form>
            </div>
        </div>
    </div>

    <div class="perm-sticky" :class="kbOpen ? 'perm-sticky-hide' : ''">
        <button type="button" x-show="step === 'phone'" x-cloak
                class="w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl inline-flex items-center justify-center gap-2 disabled:opacity-50 min-h-[3.1rem] text-base"
                :disabled="!accountFound"
                @click="goDetails()">
            Continue
        </button>
        <button type="submit" form="perm-form" x-show="step === 'details'" x-cloak
                class="w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl inline-flex items-center justify-center gap-2 disabled:opacity-50 min-h-[3.1rem] text-base"
                :disabled="!accountFound">
            {{ $otpOk ? 'Submit request' : 'Send WhatsApp code' }}
        </button>
    </div>
</div>

@push('scripts')
<script>
function permissionApply() {
    return {
        countries: @json($countries),
        countryCode: @json($countryCode),
        ccQuery: '',
        ccOpen: false,
        kbOpen: false,
        lookingUp: false,
        lookupTimer: null,
        step: @json($startDetails ? 'details' : 'phone'),
        phoneLocal: @json(old('phone', $phoneLocal)),
        fullName: @json(old('full_name', $draft['full_name'] ?? optional($user)->name ?? '')),
        companyRole: @json(old('company_role', $draft['company_role'] ?? optional($user)->role ?? '')),
        existingUserId: @json(old('existing_user_id', $draft['existing_user_id'] ?? optional($user)->id ?? '')),
        accountFound: @json((bool) ($user || old('existing_user_id', $draft['existing_user_id'] ?? ''))),
        lookupMessage: '',
        otpAlreadyOk: @json((bool) $otpOk),
        boot() {
            this.phoneLocal = this.nationalDigits(this.phoneLocal);
            if ((this.phoneLocal || '').length >= 8) this.lookupPhone();
            var self = this;
            if (window.visualViewport) {
                var onVp = function () {
                    self.kbOpen = (window.innerHeight - window.visualViewport.height) > 120;
                };
                window.visualViewport.addEventListener('resize', onVp);
                window.visualViewport.addEventListener('scroll', onVp);
            }
        },
        digitsOnly(v) {
            return String(v || '').replace(/\D/g, '');
        },
        nationalDigits(v) {
            var d = this.digitsOnly(v);
            if (d.charAt(0) === '0') d = d.replace(/^0+/, '');
            var cc = this.digitsOnly(this.countryCode);
            if (cc && d.indexOf(cc) === 0 && (d.length - cc.length) >= 7) {
                d = d.slice(cc.length);
            }
            if (cc !== '237' && d.indexOf('237') === 0) {
                var rest = d.slice(3);
                if (rest.length >= 7 && rest.length <= 10) d = rest;
            }
            return d;
        },
        prettyRole(role) {
            if (!role) return '';
            var map = { customer: 'Customer', client: 'Customer', staff: 'Staff', admin: 'Admin' };
            if (map[role]) return map[role];
            return role.replace(/_/g, ' ');
        },
        ccLabel() {
            var hit = this.countries.find(function (c) { return c.code === this.countryCode; }.bind(this));
            return hit ? hit.label : this.countryCode;
        },
        displayNumber() {
            return (this.countryCode || '') + ' ' + (this.phoneLocal || '');
        },
        filteredCountries() {
            var q = (this.ccQuery || '').trim().toLowerCase();
            if (!q) return this.countries;
            return this.countries.filter(function (c) {
                return (c.label + ' ' + c.code).toLowerCase().indexOf(q) !== -1;
            });
        },
        selectCountry(c) {
            this.countryCode = c.code;
            this.ccOpen = false;
            this.ccQuery = '';
            this.phoneLocal = this.nationalDigits(this.phoneLocal);
            this.lookupPhone();
        },
        onPhoneInput() {
            this.phoneLocal = this.nationalDigits(this.phoneLocal);
            this.accountFound = false;
            this.existingUserId = '';
            this.lookupMessage = '';
            clearTimeout(this.lookupTimer);
            var self = this;
            this.lookupTimer = setTimeout(function () { self.lookupPhone(); }, 450);
        },
        goDetails() {
            if (!this.accountFound) return;
            this.step = 'details';
            window.scrollTo(0, 0);
            this.$nextTick(function () { if (window.lucide) lucide.createIcons(); });
        },
        lookupPhone() {
            this.phoneLocal = this.nationalDigits(this.phoneLocal);
            var local = this.phoneLocal;
            if (local.length < 8) {
                this.accountFound = false;
                this.existingUserId = '';
                this.lookupMessage = '';
                this.lookingUp = false;
                return;
            }
            this.lookingUp = true;
            var url = '{{ route('beyond.permissions.lookup') }}'
                + '?country_code=' + encodeURIComponent(this.countryCode)
                + '&phone=' + encodeURIComponent(local);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    this.lookingUp = false;
                    if (data.found) {
                        this.accountFound = true;
                        this.existingUserId = data.id || '';
                        this.fullName = data.name || this.fullName;
                        if (data.role && !this.companyRole) this.companyRole = this.prettyRole(data.role);
                        this.lookupMessage = '';
                    } else {
                        this.accountFound = false;
                        this.existingUserId = '';
                        this.lookupMessage = data.message || 'No account for this number. Check the country code.';
                    }
                    if (window.lucide) lucide.createIcons();
                }.bind(this))
                .catch(function () {
                    this.lookingUp = false;
                    this.accountFound = false;
                    this.lookupMessage = 'Could not check this number. Try again.';
                }.bind(this));
        }
    }
}
</script>
@endpush
@endsection

@extends('beyond.layout')

@section('title', 'Apply for Permission')
@section('meta_description', 'Request time-off permission from Beyond Enterprise.')

@php
    $startStep = 'phone';
    if ($verifyStep) {
        $startStep = 'otp';
    } elseif (old('full_name') || ($draft['full_name'] ?? null) || ($user && $user->phone)) {
        $startStep = 'details';
    }
@endphp

@section('content')
@include('beyond.apply.partials.apply_styles')
<style>
    .perm-kicker { letter-spacing: .16em; font-size: .65rem; font-weight: 800; text-transform: uppercase; color: #d4af37; }
    .perm-label { display: flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 700; color: #0b3f90; }
    .perm-hint { font-size: .72rem; color: #64748b; margin-top: .3rem; line-height: 1.35; }
    .perm-step { display: flex; gap: .75rem; align-items: flex-start; padding: .75rem .65rem; border-radius: .9rem; margin: 0; border: 1px solid transparent; transition: background .15s, border-color .15s; }
    .perm-step-n {
        flex-shrink: 0; width: 1.85rem; height: 1.85rem; border-radius: 999px;
        background: #eef2f7; color: #64748b; font-weight: 800; font-size: .75rem;
        display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;
    }
    .perm-step.is-now { background: #eef4ff; border-color: rgba(11,63,144,.18); }
    .perm-step.is-now .perm-step-n { background: #0b3f90; color: #fff; border-color: #0b3f90; }
    .perm-step.is-done { opacity: 1; }
    .perm-step.is-done .perm-step-n { background: #d4af37; color: #002855; border-color: #d4af37; }
    .perm-step.is-todo { opacity: .55; }
    .perm-step-btn { width: 100%; text-align: left; background: none; border: 0; padding: 0; cursor: pointer; }
    .perm-step-btn:disabled { cursor: default; }
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
            <p class="perm-kicker m-0">Permission request</p>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight mt-2 mb-2">Apply for Permission</h1>
            <p class="text-blue-100 text-base md:text-lg max-w-2xl m-0 leading-relaxed">
                WhatsApp first. We find your name, you add job title, subject and reason, then verify with a code.
            </p>
            <div class="flex flex-wrap gap-2 mt-3">
                <template x-for="s in stages" :key="s.id">
                    <span class="text-[11px] font-bold rounded-full px-2.5 py-1 border"
                          :class="stageStatus(s.id) === 'now' ? 'bg-brand-gold text-brand-dark border-brand-gold' : (stageStatus(s.id) === 'done' ? 'bg-white/15 border-brand-gold/50 text-white' : 'bg-white/10 border-white/15')">
                        <span x-text="s.n + ' · ' + s.title"></span>
                    </span>
                </template>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 -mt-8 relative z-10 perm-wrap">
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <aside class="lg:col-span-4 apply-panel p-5 md:p-7 mb-2 lg:mb-0">
                <h2 class="text-lg font-extrabold text-brand-blue m-0 mb-1">Your progress</h2>
                <p class="text-sm text-slate-500 m-0 mb-3">Each stage lights up as you complete it.</p>
                <div class="space-y-1.5">
                    <template x-for="s in stages" :key="s.id">
                        <button type="button" class="perm-step-btn" @click="goStage(s.id)" :disabled="stageStatus(s.id) === 'todo'">
                            <div class="perm-step" :class="'is-' + stageStatus(s.id)">
                                <span class="perm-step-n" x-text="stageStatus(s.id) === 'done' ? '✓' : s.n"></span>
                                <div>
                                    <p class="font-bold m-0" :class="stageStatus(s.id) === 'now' ? 'text-brand-blue' : 'text-slate-800'" x-text="s.title"></p>
                                    <p class="text-sm m-0 mt-0.5" :class="stageStatus(s.id) === 'now' ? 'text-slate-600' : 'text-slate-500'" x-text="s.hint"></p>
                                </div>
                            </div>
                        </button>
                    </template>
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
                      @submit="if (step !== 'details') $event.preventDefault()">
                    @csrf
                    <input type="hidden" name="existing_user_id" x-model="existingUserId">
                    <input type="hidden" name="country_code" :value="countryCode">
                    <input type="hidden" name="phone" :value="phoneLocal">
                    <input type="hidden" name="full_name" :value="fullName">

                    <div x-show="step === 'phone'" x-cloak>
                        <div class="perm-section">
                            <h3><i data-lucide="smartphone" class="w-4 h-4 text-brand-gold"></i> Your WhatsApp number</h3>
                            <p class="text-sm text-slate-500 m-0 mb-3">Pick any country, then enter the number — customer or portal accounts, or mobile money (MTN / Orange).</p>
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
                                       @keydown.enter.prevent="canContinuePhone() ? goName() : lookupPhone()"
                                       type="text" inputmode="numeric" autocomplete="off"
                                       enterkeyhint="search"
                                       placeholder="National number">
                            </div>
                            <p class="perm-hint">Do not type a country code in this box.</p>
                        </div>

                        <div x-show="lookingUp" x-cloak class="perm-status bg-slate-50 border border-slate-200 text-slate-600">
                            <i data-lucide="loader" class="w-4 h-4 mt-0.5 shrink-0 animate-spin"></i>
                            <p class="m-0">Checking this number…</p>
                        </div>
                        <div x-show="!lookingUp && lookupMessage" x-cloak class="perm-status"
                             :class="accountFound ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-amber-50 border border-amber-200 text-amber-900'">
                            <p class="m-0" x-text="lookupMessage"></p>
                        </div>

                        <button type="button"
                                class="perm-inline-submit w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl inline-flex items-center justify-center gap-2 disabled:opacity-50 min-h-[3.1rem]"
                                :disabled="!canContinuePhone()"
                                @click="goName()">
                            Continue
                        </button>
                    </div>

                    <div x-show="step === 'name'" x-cloak>
                        <div class="perm-section">
                            <h3><i data-lucide="user" class="w-4 h-4 text-brand-gold"></i> Your name</h3>
                            <p class="text-sm text-slate-500 m-0 mb-3" x-text="nameHint()"></p>
                            <label class="perm-label" for="perm-name">Full name *</label>
                            <input id="perm-name" x-model="fullName" class="apply-field" autocomplete="name"
                                   placeholder="Edit if this is not right">
                            <p class="perm-hint" x-show="willCreate" x-cloak>No portal account yet — we will create one after you verify WhatsApp.</p>
                        </div>
                        <button type="button"
                                class="perm-inline-submit w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl min-h-[3.1rem] disabled:opacity-50"
                                :disabled="!(fullName || '').trim()"
                                @click="goDetails()">Continue</button>
                    </div>

                    <div x-show="step === 'details'" x-cloak>
                        <div class="perm-name-card mb-4">
                            <div class="h-11 w-11 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center shrink-0">
                                <i data-lucide="check" class="w-5 h-5 text-emerald-600"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="m-0 text-xs font-bold uppercase tracking-wide text-slate-500">Name</p>
                                <p class="m-0 font-extrabold text-brand-blue truncate" x-text="fullName || '—'"></p>
                                <p class="m-0 text-xs text-slate-500" x-text="displayNumber()"></p>
                            </div>
                            <button type="button" class="ml-auto text-sm font-bold text-brand-blue shrink-0" @click="step = 'name'">Edit</button>
                        </div>

                        <div class="perm-section">
                            <h3><i data-lucide="clipboard-list" class="w-4 h-4 text-brand-gold"></i> Your request</h3>
                            <div class="space-y-3.5">
                                <div>
                                    <label class="perm-label" for="perm-role">Job title *</label>
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
                                class="perm-inline-submit w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl min-h-[3.1rem]">
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

    <div class="perm-sticky" :class="(kbOpen || step === 'otp') ? 'perm-sticky-hide' : ''">
        <button type="button" x-show="step === 'phone'" x-cloak
                class="w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl min-h-[3.1rem] text-base disabled:opacity-50"
                :disabled="!canContinuePhone()"
                @click="goName()">Continue</button>
        <button type="button" x-show="step === 'name'" x-cloak
                class="w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl min-h-[3.1rem] text-base disabled:opacity-50"
                :disabled="!(fullName || '').trim()"
                @click="goDetails()">Continue</button>
        <button type="submit" form="perm-form" x-show="step === 'details'" x-cloak
                class="w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl min-h-[3.1rem] text-base">
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
        lookupDone: false,
        willCreate: false,
        stages: [
            { id: 'phone', n: 1, title: 'WhatsApp', hint: 'Choose your country and enter the number.' },
            { id: 'name', n: 2, title: 'Find your name', hint: 'From customer, portal, or mobile money — you can edit it.' },
            { id: 'details', n: 3, title: 'Job title & request', hint: 'Title, subject, period, and reason.' },
            { id: 'otp', n: 4, title: 'WhatsApp code', hint: 'Verify, then we submit the request.' }
        ],
        step: @json($startStep),
        phoneLocal: @json(old('phone', $phoneLocal)),
        fullName: @json(old('full_name', $draft['full_name'] ?? optional($user)->name ?? '')),
        companyRole: @json(old('company_role', $draft['company_role'] ?? '')),
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
        stageStatus(id) {
            var order = this.stages.map(function (s) { return s.id; });
            var cur = order.indexOf(this.step);
            var idx = order.indexOf(id);
            if (idx < 0 || cur < 0) return 'todo';
            if (idx < cur) return 'done';
            if (idx === cur) return 'now';
            return 'todo';
        },
        goStage(id) {
            if (this.stageStatus(id) === 'todo') return;
            this.step = id;
            window.scrollTo(0, 0);
            this.$nextTick(function () { if (window.lucide) lucide.createIcons(); });
        },
        canContinuePhone() {
            return !this.lookingUp && this.digitsOnly(this.phoneLocal).length >= 8 && this.lookupDone;
        },
        goName() {
            if (!this.canContinuePhone()) return;
            this.step = 'name';
            window.scrollTo(0, 0);
            this.$nextTick(function () { if (window.lucide) lucide.createIcons(); });
        },
        nameHint() {
            if (this.accountFound) return 'We found this name on your account. You can edit it.';
            if (this.willCreate && (this.fullName || '').trim()) return 'Name from this mobile-money number. Edit if it is not right.';
            return 'Enter the name we should put on the letter.';
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
            this.willCreate = false;
            this.existingUserId = '';
            this.lookupMessage = '';
            this.lookupDone = false;
            clearTimeout(this.lookupTimer);
            var self = this;
            this.lookupTimer = setTimeout(function () { self.lookupPhone(); }, 450);
        },
        goDetails() {
            if (!(this.fullName || '').trim()) return;
            this.step = 'details';
            window.scrollTo(0, 0);
            this.$nextTick(function () { if (window.lucide) lucide.createIcons(); });
        },
        lookupPhone() {
            this.phoneLocal = this.nationalDigits(this.phoneLocal);
            var local = this.phoneLocal;
            if (local.length < 8) {
                this.accountFound = false;
                this.willCreate = false;
                this.existingUserId = '';
                this.lookupMessage = '';
                this.lookingUp = false;
                this.lookupDone = false;
                return;
            }
            this.lookingUp = true;
            this.lookupDone = false;
            var url = '{{ route('beyond.permissions.lookup') }}'
                + '?country_code=' + encodeURIComponent(this.countryCode)
                + '&phone=' + encodeURIComponent(local);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    this.lookingUp = false;
                    this.lookupDone = true;
                    if (data.found) {
                        this.accountFound = true;
                        this.willCreate = false;
                        this.existingUserId = data.id || '';
                        if (data.name) this.fullName = data.name;
                        this.lookupMessage = data.message || 'We found your name. Continue to confirm it.';
                    } else {
                        this.accountFound = false;
                        this.willCreate = !!data.will_create;
                        this.existingUserId = '';
                        if (data.name) this.fullName = data.name;
                        this.lookupMessage = data.message || 'Enter your name on the next step.';
                    }
                    if (window.lucide) lucide.createIcons();
                }.bind(this))
                .catch(function () {
                    this.lookingUp = false;
                    this.lookupDone = true;
                    this.accountFound = false;
                    this.willCreate = true;
                    this.lookupMessage = 'Could not check this number. You can still continue and enter your name.';
                }.bind(this));
        }
    }
}
</script>
@endpush
@endsection

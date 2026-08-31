@extends('beyond.layout')

@section('title', 'Apply for Permission')
@section('meta_description', 'Request time-off permission from Beyond Enterprise.')

@section('content')
@include('beyond.apply.partials.apply_styles')
<style>
    .perm-kicker { letter-spacing: .18em; font-size: .68rem; font-weight: 800; text-transform: uppercase; color: #d4af37; }
    .perm-label { display: flex; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 700; color: #0b3f90; }
    .perm-hint { font-size: .75rem; color: #64748b; margin-top: .35rem; }
    .perm-step {
        display: flex; gap: .75rem; align-items: flex-start;
        padding: .85rem 0;
    }
    .perm-step-n {
        flex-shrink: 0; width: 1.85rem; height: 1.85rem; border-radius: 999px;
        background: rgba(212,175,55,.15); color: #c6ab47; font-weight: 800; font-size: .75rem;
        display: flex; align-items: center; justify-content: center; border: 1px solid rgba(212,175,55,.35);
    }
    .perm-section {
        border: 1px solid #eef2f7; border-radius: 1.05rem; padding: 1.1rem 1.15rem 1.2rem;
        background: linear-gradient(180deg, #fbfcfe 0%, #fff 40%);
    }
    .perm-section + .perm-section { margin-top: 1rem; }
    .perm-section h3 { margin: 0 0 .9rem; font-size: .92rem; font-weight: 800; color: #0b3f90; display: flex; align-items: center; gap: .45rem; }
    .perm-otp {
        border: 1.5px solid rgba(212,175,55,.45); background: linear-gradient(180deg, #fffbeb, #fff);
        border-radius: 1.15rem; padding: 1.25rem 1.3rem; box-shadow: 0 12px 32px rgba(198,171,71,.12);
    }
    .perm-form-card { border-top: 4px solid #d4af37; }
</style>

<div class="min-h-screen apply-shell pb-16" x-data="permissionApply()" x-init="if ((phoneLocal || '').trim().length >= 6) lookupPhone()">
    <div class="relative overflow-hidden bg-brand-blue text-white">
        <div class="absolute inset-0 opacity-30" style="background:linear-gradient(120deg,rgba(212,175,55,.38),transparent 48%),radial-gradient(circle at 85% 15%,rgba(255,255,255,.14),transparent 42%);"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-10 md:py-14">
            <p class="perm-kicker m-0">Staff · Existing accounts</p>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight mt-2 mb-3">Apply for Permission</h1>
            <p class="text-blue-100 text-base md:text-lg max-w-2xl m-0 leading-relaxed">
                Request leave for a date and time. We send a signed letter after approval — verify with WhatsApp OTP.
            </p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 -mt-8 relative z-10">
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <aside class="lg:col-span-4 apply-panel p-6 md:p-7 order-2 lg:order-1">
                <h2 class="text-lg font-extrabold text-brand-blue m-0 mb-1">How it works</h2>
                <p class="text-sm text-slate-500 m-0 mb-4">Three short steps. No new account needed.</p>
                <div class="divide-y divide-slate-100">
                    <div class="perm-step">
                        <span class="perm-step-n">1</span>
                        <div>
                            <p class="font-bold text-slate-800 m-0">Your request</p>
                            <p class="text-sm text-slate-500 m-0 mt-0.5">Name, subject, and the period you need.</p>
                        </div>
                    </div>
                    <div class="perm-step">
                        <span class="perm-step-n">2</span>
                        <div>
                            <p class="font-bold text-slate-800 m-0">Confirm WhatsApp</p>
                            <p class="text-sm text-slate-500 m-0 mt-0.5">We match your number to your staff account and send a code.</p>
                        </div>
                    </div>
                    <div class="perm-step">
                        <span class="perm-step-n">3</span>
                        <div>
                            <p class="font-bold text-slate-800 m-0">Await the letter</p>
                            <p class="text-sm text-slate-500 m-0 mt-0.5">Approved or denied, you receive an official letter on WhatsApp.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 rounded-xl bg-brand-blue text-white p-4">
                    <p class="text-brand-gold text-xs font-extrabold uppercase tracking-wider m-0 mb-1">Tip</p>
                    <p class="text-sm text-blue-100 m-0 leading-relaxed">Use the WhatsApp number already on your account so your name loads automatically.</p>
                </div>
            </aside>

            <div class="lg:col-span-8 apply-panel perm-form-card p-5 sm:p-7 md:p-8 order-1 lg:order-2">
                @if(session('success'))
                    <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-medium">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        <ul class="list-disc pl-5 m-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                @if($verifyStep)
                    <div class="perm-otp mb-6">
                        <p class="perm-kicker m-0 mb-2">Almost done</p>
                        <h2 class="text-xl font-extrabold text-brand-blue m-0 mb-1">Verify WhatsApp OTP</h2>
                        <p class="text-sm text-slate-600 m-0 mb-4">Enter the code sent to <strong>{{ $maskedPhone }}</strong>.</p>
                        <form method="POST" action="{{ route('beyond.permissions.verify') }}" class="space-y-3">
                            @csrf
                            <input type="text" name="otp" maxlength="6" required inputmode="numeric" autocomplete="one-time-code"
                                   class="apply-field text-center text-2xl tracking-[0.4em] font-extrabold"
                                   placeholder="••••••">
                            <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3.5 rounded-xl inline-flex items-center justify-center gap-2">
                                <i data-lucide="shield-check" class="w-5 h-5"></i> Verify &amp; submit
                            </button>
                        </form>
                        <form method="POST" action="{{ route('beyond.permissions.resend') }}" class="mt-3 text-center">
                            @csrf
                            <button type="submit" class="text-sm text-brand-light font-semibold hover:underline">Resend code</button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('beyond.permissions.store') }}">
                    @csrf
                    <input type="hidden" name="existing_user_id" x-model="existingUserId">

                    <div class="perm-section">
                        <h3><i data-lucide="user" class="w-4 h-4 text-brand-gold"></i> Your request</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="perm-label">Full name *</label>
                                <input required name="full_name" x-model="fullName" :readonly="accountFound"
                                       autocomplete="off"
                                       class="apply-field"
                                       :class="accountFound ? 'bg-slate-50' : ''"
                                       placeholder="Your full name">
                            </div>
                            <div>
                                <label class="perm-label">Subject *</label>
                                <input required name="subject" maxlength="255"
                                       value="{{ old('subject', $draft['subject'] ?? '') }}"
                                       class="apply-field"
                                       placeholder="Short title for this permission">
                                <p class="perm-hint">Printed on the letter as Permission Approved… your subject.</p>
                            </div>
                        </div>
                    </div>

                    <div class="perm-section">
                        <h3><i data-lucide="smartphone" class="w-4 h-4 text-brand-gold"></i> Account</h3>
                        <div>
                            <label class="perm-label">WhatsApp number *</label>
                            <div class="flex gap-2 mt-1">
                                <div class="relative w-44 sm:w-52 shrink-0" @click.away="ccOpen = false">
                                    <input type="hidden" name="country_code" :value="countryCode">
                                    <button type="button" @click="ccOpen = !ccOpen; $nextTick(() => { if (ccOpen && $refs.ccSearch) $refs.ccSearch.focus(); })"
                                            class="apply-field mt-0 text-left text-sm truncate">
                                        <span x-text="ccLabel()"></span>
                                    </button>
                                    <div x-show="ccOpen" x-cloak
                                         class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden">
                                        <input type="search" x-model="ccQuery" x-ref="ccSearch"
                                               @click.stop placeholder="Search country…"
                                               class="w-full border-0 border-b border-slate-100 px-3 py-2.5 text-sm outline-none">
                                        <div class="max-h-56 overflow-auto">
                                            <template x-for="c in filteredCountries()" :key="c.code">
                                                <button type="button" @click="selectCountry(c)"
                                                        class="w-full text-left px-3 py-2.5 text-sm hover:bg-blue-50"
                                                        :class="c.code === countryCode ? 'bg-blue-50 font-semibold text-brand-blue' : ''"
                                                        x-text="c.label"></button>
                                            </template>
                                            <p class="px-3 py-2 text-xs text-gray-500" x-show="filteredCountries().length === 0">No matches.</p>
                                        </div>
                                    </div>
                                </div>
                                <input required name="phone" x-model="phoneLocal" @input.debounce.400ms="lookupPhone"
                                       class="apply-field mt-0 flex-1"
                                       placeholder="Phone number">
                            </div>
                            <p class="perm-hint">We look up your account from this number, then send a WhatsApp OTP.</p>
                        </div>

                        <div x-show="lookupMessage" x-cloak
                             class="mt-3 rounded-xl px-4 py-3 text-sm font-medium"
                             :class="accountFound ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800'">
                            <p class="m-0" x-text="lookupMessage"></p>
                        </div>

                        <div class="mt-4">
                            <label class="perm-label">Your role in the company *</label>
                            <input required name="company_role" x-model="companyRole"
                                   class="apply-field"
                                   placeholder="e.g. Technician, Engineer, Admin">
                        </div>
                    </div>

                    <div class="perm-section">
                        <h3><i data-lucide="calendar-clock" class="w-4 h-4 text-brand-gold"></i> Period</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="perm-label">From *</label>
                                <input required type="datetime-local" name="from_at"
                                       value="{{ old('from_at', $draft['from_at'] ?? '') }}"
                                       class="apply-field">
                            </div>
                            <div>
                                <label class="perm-label">To *</label>
                                <input required type="datetime-local" name="to_at"
                                       value="{{ old('to_at', $draft['to_at'] ?? '') }}"
                                       class="apply-field">
                            </div>
                        </div>
                    </div>

                    <div class="perm-section">
                        <h3><i data-lucide="align-left" class="w-4 h-4 text-brand-gold"></i> Explanation</h3>
                        <label class="perm-label">Reason for permission *</label>
                        <textarea required name="reason" rows="4"
                                  class="apply-field"
                                  placeholder="Explain why you need this permission.">{{ old('reason', $draft['reason'] ?? '') }}</textarea>
                    </div>

                    <button type="submit"
                            class="mt-5 w-full bg-brand-gold hover:bg-[#c6ab47] text-brand-dark font-extrabold py-3.5 rounded-xl shadow-lg shadow-amber-200/50 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:shadow-none"
                            :disabled="!accountFound">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        {{ $otpOk ? 'Submit permission request' : 'Continue with WhatsApp OTP' }}
                    </button>

                    <p class="text-center text-sm text-slate-500 mt-4 mb-0">
                        Already have an account?
                        <a href="{{ url('/login?redirect=/permissions') }}" class="text-brand-blue font-bold hover:underline">Sign in</a>
                        ·
                        <a href="{{ url('/forgot-password') }}" class="text-brand-blue font-bold hover:underline">Reset password</a>
                    </p>
                </form>
            </div>
        </div>
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
        phoneLocal: @json(old('phone', $phoneLocal)),
        fullName: @json(old('full_name', $draft['full_name'] ?? optional($user)->name ?? '')),
        companyRole: @json(old('company_role', $draft['company_role'] ?? optional($user)->role ?? '')),
        existingUserId: @json(old('existing_user_id', $draft['existing_user_id'] ?? optional($user)->id ?? '')),
        accountFound: @json((bool) ($user || old('existing_user_id', $draft['existing_user_id'] ?? ''))),
        lookupMessage: '',
        otpAlreadyOk: @json((bool) $otpOk),
        ccLabel() {
            const hit = this.countries.find(c => c.code === this.countryCode);
            return hit ? hit.label : this.countryCode;
        },
        filteredCountries() {
            const q = (this.ccQuery || '').trim().toLowerCase();
            if (!q) return this.countries;
            return this.countries.filter(c => (c.label + ' ' + c.code).toLowerCase().indexOf(q) !== -1);
        },
        selectCountry(c) {
            this.countryCode = c.code;
            this.ccOpen = false;
            this.ccQuery = '';
            this.lookupPhone();
        },
        lookupPhone() {
            const local = (this.phoneLocal || '').trim();
            if (local.length < 6) {
                this.accountFound = false;
                this.existingUserId = '';
                this.lookupMessage = '';
                return;
            }
            const url = '{{ route('beyond.permissions.lookup') }}'
                + '?country_code=' + encodeURIComponent(this.countryCode)
                + '&phone=' + encodeURIComponent(local);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        this.accountFound = true;
                        this.existingUserId = data.id || '';
                        this.fullName = data.name || this.fullName;
                        if (data.role && !this.companyRole) this.companyRole = data.role;
                        this.lookupMessage = 'Account found: ' + (data.name || 'staff')
                            + (data.phone_masked ? ' (' + data.phone_masked + ')' : '') + '.';
                    } else {
                        this.accountFound = false;
                        this.existingUserId = '';
                        this.lookupMessage = data.message || 'No account is linked to this WhatsApp number.';
                    }
                    if (window.lucide) lucide.createIcons();
                })
                .catch(() => {
                    this.accountFound = false;
                    this.lookupMessage = 'Could not look up this number. Try again.';
                });
        }
    }
}
</script>
@endpush
@endsection

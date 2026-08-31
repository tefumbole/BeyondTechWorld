@extends('beyond.layout')

@section('title', 'Apply for Permission')
@section('meta_description', 'Request time-off permission from Beyond Enterprise.')

@section('content')
<div class="min-h-screen bg-gray-50 pb-20" x-data="permissionApply()" x-init="if ((phoneLocal || '').trim().length >= 6) lookupPhone()">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-16 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-4">Apply for Permission</h1>
            <p class="text-lg text-blue-100">For existing accounts only. Enter your WhatsApp number to load your name, then verify with OTP.</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 -mt-8">
        <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-6 md:p-8">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                    <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @if($verifyStep)
                <div class="mb-8 border border-brand-gold/40 bg-amber-50 rounded-lg p-5">
                    <h2 class="text-lg font-bold text-brand-blue mb-1">Verify WhatsApp OTP</h2>
                    <p class="text-sm text-gray-600 mb-4">Enter the code sent to <strong>{{ $maskedPhone }}</strong> to submit this request.</p>
                    <form method="POST" action="{{ route('beyond.permissions.verify') }}" class="space-y-3">
                        @csrf
                        <input type="text" name="otp" maxlength="6" required inputmode="numeric" autocomplete="one-time-code"
                               class="w-full rounded-md border border-gray-200 px-3 py-3 text-center text-2xl tracking-widest font-bold"
                               placeholder="••••••">
                        <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md">
                            Verify & Submit Request
                        </button>
                    </form>
                    <form method="POST" action="{{ route('beyond.permissions.resend') }}" class="mt-3 text-center">
                        @csrf
                        <button type="submit" class="text-sm text-brand-light hover:underline">Resend code</button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ route('beyond.permissions.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="existing_user_id" x-model="existingUserId">

                <div>
                    <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                    <input required name="full_name" x-model="fullName" :readonly="accountFound"
                           autocomplete="off"
                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                           :class="accountFound ? 'bg-gray-50' : ''"
                           placeholder="Your full name">
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">Subject *</label>
                    <input required name="subject" maxlength="255"
                           value="{{ old('subject', $draft['subject'] ?? '') }}"
                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                           placeholder="Short title for this permission">
                    <p class="text-xs text-gray-500 mt-1">This becomes the letter subject, e.g. Permission Approved… your subject.</p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">WhatsApp number *</label>
                    <div class="flex gap-2 mt-1">
                        <div class="relative w-52 shrink-0" @click.away="ccOpen = false">
                            <input type="hidden" name="country_code" :value="countryCode">
                            <button type="button" @click="ccOpen = !ccOpen; $nextTick(() => { if (ccOpen && $refs.ccSearch) $refs.ccSearch.focus(); })"
                                    class="w-full rounded-md border border-gray-200 px-2 py-2 text-left text-sm bg-white truncate">
                                <span x-text="ccLabel()"></span>
                            </button>
                            <div x-show="ccOpen" x-cloak
                                 class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg overflow-hidden">
                                <input type="search" x-model="ccQuery" x-ref="ccSearch"
                                       @click.stop placeholder="Search country…"
                                       class="w-full border-0 border-b border-gray-100 px-3 py-2 text-sm outline-none">
                                <div class="max-h-56 overflow-auto">
                                    <template x-for="c in filteredCountries()" :key="c.code">
                                        <button type="button" @click="selectCountry(c)"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50"
                                                :class="c.code === countryCode ? 'bg-blue-50 font-semibold' : ''"
                                                x-text="c.label"></button>
                                    </template>
                                    <p class="px-3 py-2 text-xs text-gray-500" x-show="filteredCountries().length === 0">No matches.</p>
                                </div>
                            </div>
                        </div>
                        <input required name="phone" x-model="phoneLocal" @input.debounce.400ms="lookupPhone"
                               class="flex-1 rounded-md border border-gray-200 px-3 py-2"
                               placeholder="Phone number">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">We look up your account from this number, then send a WhatsApp OTP.</p>
                </div>

                <div x-show="lookupMessage" x-cloak
                     class="rounded-lg px-4 py-3 text-sm"
                     :class="accountFound ? 'bg-blue-50 border border-blue-200 text-blue-900' : 'bg-red-50 border border-red-200 text-red-800'">
                    <p x-text="lookupMessage"></p>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">Your role in the company *</label>
                    <input required name="company_role" x-model="companyRole"
                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                           placeholder="e.g. Technician, Engineer, Admin">
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-700">From (date & time) *</label>
                        <input required type="datetime-local" name="from_at"
                               value="{{ old('from_at', $draft['from_at'] ?? '') }}"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">To (date & time) *</label>
                        <input required type="datetime-local" name="to_at"
                               value="{{ old('to_at', $draft['to_at'] ?? '') }}"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">Reason for permission *</label>
                    <textarea required name="reason" rows="4"
                              class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                              placeholder="Explain why you need this permission.">{{ old('reason', $draft['reason'] ?? '') }}</textarea>
                </div>

                <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md disabled:opacity-50"
                        :disabled="!accountFound">
                    {{ $otpOk ? 'Submit Permission Request' : 'Continue with WhatsApp OTP' }}
                </button>

                <p class="text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ url('/login?redirect=/permissions') }}" class="text-brand-blue font-semibold hover:underline">Sign in</a>
                    ·
                    <a href="{{ url('/forgot-password') }}" class="text-brand-blue font-semibold hover:underline">Reset password</a>
                </p>
            </form>
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

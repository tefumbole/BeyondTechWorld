@extends('beyond.layout')

@section('title', 'Apply for Permission')
@section('meta_description', 'Request time-off permission from Beyond Enterprise.')

@section('content')
<div class="min-h-screen bg-gray-50 pb-20" x-data="permissionApply()">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-16 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-4">Apply for Permission</h1>
            <p class="text-lg text-blue-100">Request leave for a date and time range. Available for staff from any country — verify with WhatsApp OTP.</p>
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
                    <p class="text-sm text-gray-600 mb-4">Enter the code sent to <strong>{{ $maskedPhone }}</strong> to create/confirm your account and submit this request.</p>
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

            <form method="POST" action="{{ route('beyond.permissions.store') }}" class="space-y-4" @submit="onSubmit">
                @csrf
                <input type="hidden" name="existing_user_id" x-model="existingUserId">

                <div class="relative">
                    <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                    <input required name="full_name" x-model="fullName" @input.debounce.300ms="searchNames"
                           autocomplete="off"
                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                           placeholder="Start typing your name…">
                    <div x-show="suggestions.length" x-cloak
                         class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-56 overflow-auto">
                        <template x-for="item in suggestions" :key="item.id">
                            <button type="button" @click="selectPerson(item)"
                                    class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-50 last:border-0">
                                <div class="font-semibold text-gray-800" x-text="item.name"></div>
                                <div class="text-xs text-gray-500">
                                    <span x-text="item.phone_masked || 'No phone on file'"></span>
                                    <span x-show="item.email"> · <span x-text="item.email"></span></span>
                                    <span x-show="item.has_account" class="text-green-700 font-semibold"> · Account found</span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="matchNotice" x-cloak class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-900">
                    <p class="font-semibold mb-1">We found a matching profile</p>
                    <p class="mb-2" x-text="matchNotice"></p>
                    <div class="flex flex-wrap gap-3">
                        <a :href="loginUrl" class="text-brand-blue font-bold underline">Sign in</a>
                        <a :href="resetUrl" class="text-brand-blue font-bold underline">Forgot password? Reset via WhatsApp OTP</a>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Email</label>
                        <input type="email" name="email" x-model="email"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Your role in the company *</label>
                        <input required name="company_role"
                               value="{{ old('company_role', $draft['company_role'] ?? '') }}"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                               placeholder="e.g. Technician, Engineer, Admin">
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">WhatsApp number *</label>
                    <div class="flex gap-2 mt-1">
                        <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2 w-44 shrink-0">
                            @foreach($countryCodes as $code => $label)
                                <option value="{{ $code }}" @if(old('country_code', $draft['country_code'] ?? '+237') === $code) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input required name="phone" x-model="phoneLocal"
                               class="flex-1 rounded-md border border-gray-200 px-3 py-2"
                               placeholder="Phone number (any country)">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">We use this number for OTP verification and status updates.</p>
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
                              placeholder="Why do you need this permission?">{{ old('reason', $draft['reason'] ?? '') }}</textarea>
                </div>

                <div class="border-t border-gray-100 pt-4" x-show="!otpAlreadyOk" x-cloak>
                    <p class="text-sm font-semibold text-gray-700 mb-2">New account password <span class="font-normal text-gray-500">(optional — if you don’t have an account yet)</span></p>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <input type="password" name="password" minlength="8"
                                   class="w-full rounded-md border border-gray-200 px-3 py-2" placeholder="Password (min 8 chars)">
                        </div>
                        <div>
                            <input type="password" name="password_confirmation" minlength="8"
                                   class="w-full rounded-md border border-gray-200 px-3 py-2" placeholder="Confirm password">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Leave blank to receive a temporary password on WhatsApp after OTP verification.</p>
                </div>

                <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3 rounded-md">
                    {{ $otpOk ? 'Submit Permission Request' : 'Continue with WhatsApp OTP' }}
                </button>

                <p class="text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ url('/login?redirect=/permissions') }}" class="text-brand-blue font-semibold hover:underline">Sign in</a>
                    ·
                    <a href="{{ url('/login?tab=signup&redirect=/permissions') }}" class="text-brand-blue font-semibold hover:underline">Sign up</a>
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
        fullName: @json(old('full_name', $draft['full_name'] ?? optional($user)->name ?? '')),
        email: @json(old('email', $draft['email'] ?? optional($user)->email ?? '')),
        phoneLocal: @json(old('phone', $draft['phone'] ?? '')),
        existingUserId: @json(old('existing_user_id', $draft['existing_user_id'] ?? '')),
        suggestions: [],
        matchNotice: '',
        otpAlreadyOk: @json((bool) $otpOk),
        loginUrl: '{{ url('/login?redirect=/permissions') }}',
        resetUrl: '{{ url('/forgot-password') }}',
        searchNames() {
            this.existingUserId = '';
            this.matchNotice = '';
            const q = (this.fullName || '').trim();
            if (q.length < 2) {
                this.suggestions = [];
                return;
            }
            fetch('{{ route('beyond.permissions.search') }}?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => r.json())
                .then(data => { this.suggestions = data.results || []; })
                .catch(() => { this.suggestions = []; });
        },
        selectPerson(item) {
            this.fullName = item.name || '';
            this.email = item.email || this.email;
            this.existingUserId = item.source === 'beyond' ? item.id : '';
            this.suggestions = [];
            if (item.phone) {
                // Best-effort local digits (strip country code guesses)
                const digits = String(item.phone).replace(/\D/g, '');
                this.phoneLocal = digits.length > 9 ? digits.slice(-9) : digits;
            }
            if (item.has_account) {
                this.matchNotice = (item.name || 'This person') + ' already has an account'
                    + (item.phone_masked ? ' (' + item.phone_masked + ')' : '')
                    + '. Select Continue to verify by OTP, or sign in / reset password.';
                this.resetUrl = '{{ url('/forgot-password') }}' + (item.phone ? ('?phone=' + encodeURIComponent(item.phone)) : '');
                this.loginUrl = '{{ url('/login?redirect=/permissions') }}' + (item.email ? ('&u=' + encodeURIComponent(item.email)) : '');
            } else {
                this.matchNotice = 'Name found in company records. Enter your WhatsApp number to verify and create a portal account if needed.';
            }
        },
        onSubmit() {
            this.suggestions = [];
        }
    }
}
</script>
@endpush
@endsection

@extends('beyond.layout')

@section('title', 'Equipment Rentals')
@section('meta_description', 'Request equipment rentals from Beyond Enterprise.')

@section('content')
@php
    $countryCodes = ['+237','+250','+256','+254','+243','+233','+234','+1','+44','+33'];
@endphp
<div class="min-h-screen bg-slate-50 pb-10" x-data="rentalForm()" x-init="boot()">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-3 sm:py-3.5 px-4">
        <div class="max-w-2xl mx-auto text-center">
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Equipment Rentals</h1>
            <p class="text-xs sm:text-sm text-blue-100 mt-0.5">WhatsApp number → we find your name → pick dates &amp; request kit</p>
        </div>
    </div>

    <div class="max-w-xl mx-auto px-3 sm:px-4 -mt-2 relative z-10">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200/80 overflow-hidden">
            <div class="h-1 bg-gradient-to-r from-brand-gold via-brand-blue to-brand-gold"></div>
            <div class="p-4 sm:p-6">
                @if($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-3.5 py-2.5 text-sm">
                        <ul class="list-disc pl-5 m-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('beyond.rentals.store') }}" class="space-y-4" @submit="onSubmit">
                    @csrf

                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 sm:p-4 space-y-3">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wide text-brand-blue" for="rental-phone">WhatsApp number *</label>
                            <div class="mt-1.5 flex gap-2">
                                <select name="country_code" id="rental-cc" x-model="countryCode" @change="lookup()"
                                        class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-sm font-semibold text-brand-blue w-[5.75rem] shrink-0 focus:border-brand-blue outline-none">
                                    @foreach($countryCodes as $code)
                                        <option value="{{ $code }}" @if(old('country_code','+237')===$code) selected @endif>{{ $code }}</option>
                                    @endforeach
                                </select>
                                <input required name="phone" id="rental-phone" x-model="phoneLocal" @input="onPhoneInput()"
                                       type="tel" inputmode="numeric" autocomplete="tel-national"
                                       value="{{ old('phone') }}"
                                       class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand-blue outline-none min-w-0"
                                       placeholder="675321739">
                            </div>
                            <p class="text-[11px] mt-1.5 min-h-[1rem]" :class="statusOk ? 'text-emerald-700' : 'text-slate-500'" x-text="statusText"></p>
                        </div>

                        <div>
                            <label class="text-xs font-bold uppercase tracking-wide text-brand-blue" for="rental-name">Full name *</label>
                            <input required name="full_name" id="rental-name" x-model="fullName"
                                   value="{{ old('full_name') }}"
                                   class="w-full mt-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand-blue outline-none"
                                   placeholder="Filled automatically when we know the number"
                                   :readonly="nameLocked">
                            <p class="text-[11px] text-slate-500 mt-1" x-show="nameLocked" x-cloak>Name from our records — edit only if wrong.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wide text-brand-blue" for="rental-start">Start date *</label>
                            <input required type="date" name="start_date" id="rental-start"
                                   value="{{ old('start_date', date('Y-m-d')) }}"
                                   class="w-full mt-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand-blue outline-none">
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wide text-brand-blue" for="rental-end">End date *</label>
                            <input required type="date" name="end_date" id="rental-end"
                                   value="{{ old('end_date', date('Y-m-d')) }}"
                                   class="w-full mt-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand-blue outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold uppercase tracking-wide text-brand-blue" for="rental-kit">Equipment needed *</label>
                        <textarea required name="equipment_needed" id="rental-kit" rows="3"
                                  class="w-full mt-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand-blue outline-none resize-y min-h-[5rem]"
                                  placeholder="e.g. 2× LED screens, PA system, cameras…">{{ old('equipment_needed') }}</textarea>
                    </div>

                    <div>
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-500" for="rental-notes">Additional notes</label>
                        <textarea name="notes" id="rental-notes" rows="2"
                                  class="w-full mt-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-brand-blue outline-none resize-y"
                                  placeholder="Optional — venue, delivery, setup time…">{{ old('notes') }}</textarea>
                    </div>

                    <input type="hidden" name="email" value="{{ old('email') }}">
                    <input type="hidden" name="company_name" value="{{ old('company_name') }}">
                    <input type="hidden" name="address" value="{{ old('address') }}">

                    <button type="submit"
                            class="w-full rounded-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-extrabold py-3 text-sm sm:text-base shadow-md transition disabled:opacity-50"
                            :disabled="lookingUp">
                        Submit Booking Request
                    </button>
                    <p class="text-center text-[11px] text-slate-500 m-0">We’ll confirm on WhatsApp after review.</p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function rentalForm() {
    return {
        countryCode: @json(old('country_code', '+237')),
        phoneLocal: @json(old('phone', '')),
        fullName: @json(old('full_name', '')),
        nameLocked: false,
        lookingUp: false,
        statusText: '',
        statusOk: true,
        timer: null,
        boot() {
            if (this.digits(this.phoneLocal).length >= 8) this.lookup();
        },
        digits(v) { return String(v || '').replace(/\D/g, ''); },
        onPhoneInput() {
            this.nameLocked = false;
            this.statusText = '';
            clearTimeout(this.timer);
            var self = this;
            this.timer = setTimeout(function () { self.lookup(); }, 450);
        },
        lookup() {
            if (this.digits(this.phoneLocal).length < 8) return;
            var self = this;
            this.lookingUp = true;
            this.statusText = 'Looking up number…';
            this.statusOk = true;
            var url = @json(route('directory.phone-lookup'))
                + '?phone=' + encodeURIComponent(this.phoneLocal)
                + '&country_code=' + encodeURIComponent(this.countryCode);
            fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    self.lookingUp = false;
                    var n = (data && (data.original_name || data.name || data.system_name)) || '';
                    if (n) {
                        self.fullName = n;
                        self.nameLocked = false;
                        self.statusOk = true;
                        self.statusText = 'Found in system — name filled in.';
                    } else {
                        self.statusOk = false;
                        self.statusText = 'Not on file — please type your name.';
                    }
                })
                .catch(function () {
                    self.lookingUp = false;
                    self.statusOk = false;
                    self.statusText = 'Lookup unavailable — type your name.';
                });
        },
        onSubmit(e) {
            if (!(this.fullName || '').trim()) {
                e.preventDefault();
                this.statusOk = false;
                this.statusText = 'Name is required.';
            }
        }
    };
}
</script>
@endpush

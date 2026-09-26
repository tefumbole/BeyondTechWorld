@extends('beyond.layout')

@section('title', 'Equipment Rentals')
@section('meta_description', 'Request equipment rentals from Beyond Enterprise.')

@section('content')
@push('head')
<style>
    .rental-cc-btn {
        min-width: 5.75rem; max-width: 11rem; padding: .65rem .9rem .65rem .7rem;
        font-size: .85rem; font-weight: 800; color: #003D82; text-align: left; position: relative;
        border-radius: .75rem; border: 1px solid #e2e8f0; background: #fff;
        background-image: linear-gradient(45deg, transparent 50%, #003D82 50%), linear-gradient(135deg, #003D82 50%, transparent 50%);
        background-position: calc(100% - 12px) calc(50% - 3px), calc(100% - 7px) calc(50% - 3px);
        background-size: 5px 5px, 5px 5px; background-repeat: no-repeat;
    }
    .rental-sheet-bg { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 70; }
    .rental-sheet {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 71;
        background: #fff; border-radius: 1.25rem 1.25rem 0 0;
        max-height: min(78vh, 560px); display: flex; flex-direction: column;
        box-shadow: 0 -12px 40px rgba(15,23,42,.18);
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    @media (min-width: 640px) {
        .rental-cc-btn { min-width: 12rem; max-width: 14rem; }
        .rental-sheet-bg { display: none !important; }
        .rental-sheet {
            position: absolute; left: 0; right: auto; bottom: auto; top: 100%; margin-top: .35rem;
            width: 20rem; max-height: 18rem; border-radius: .9rem; z-index: 40;
            box-shadow: 0 12px 32px rgba(15,23,42,.14); padding-bottom: 0;
        }
    }
</style>
@endpush

<div class="min-h-screen bg-slate-50 pb-10" x-data="rentalForm()" x-init="boot()">
    <div class="bg-gradient-to-r from-brand-blue via-[#004e9a] to-brand-dark text-white py-3 sm:py-3.5 px-4">
        <div class="max-w-2xl mx-auto text-center">
            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight m-0">Equipment Rentals</h1>
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
                    <input type="hidden" name="country_code" :value="countryCode">

                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 sm:p-4 space-y-3">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wide text-brand-blue">WhatsApp number *</label>
                            <div class="mt-1.5 flex gap-2 items-stretch">
                                <div class="relative shrink-0" @click.away="ccOpen = false" @keydown.escape.window="ccOpen = false">
                                    <button type="button" class="rental-cc-btn w-full"
                                            @click="ccOpen = !ccOpen; $nextTick(() => { if (ccOpen && $refs.ccSearch) $refs.ccSearch.focus(); })"
                                            :aria-expanded="ccOpen ? 'true' : 'false'">
                                        <span class="hidden sm:inline truncate pr-3" x-text="ccLabel()"></span>
                                        <span class="sm:hidden" x-text="countryCode"></span>
                                    </button>
                                    <div x-show="ccOpen" x-cloak>
                                        <div class="rental-sheet-bg sm:hidden" @click="ccOpen = false"></div>
                                        <div class="rental-sheet">
                                            <div class="flex items-center justify-between px-4 pt-3 pb-2">
                                                <p class="m-0 text-sm font-extrabold text-brand-blue">Search country</p>
                                                <button type="button" class="text-sm font-semibold text-slate-500 sm:hidden" @click="ccOpen = false">Done</button>
                                            </div>
                                            <input type="search" x-model="ccQuery" x-ref="ccSearch"
                                                   @click.stop placeholder="Country or +code"
                                                   enterkeyhint="search" inputmode="search"
                                                   class="mx-3 mb-2 rounded-xl border border-slate-200 px-3 py-2.5 text-base outline-none focus:border-brand-blue">
                                            <div class="overflow-auto flex-1 px-1 pb-2" style="max-height: 14rem;">
                                                <template x-for="c in filteredCountries()" :key="c.code">
                                                    <button type="button" @click="selectCountry(c)"
                                                            class="w-full text-left px-3 py-2.5 text-sm rounded-lg"
                                                            :class="c.code === countryCode ? 'bg-blue-50 font-semibold text-brand-blue' : 'hover:bg-slate-50'"
                                                            x-text="c.label"></button>
                                                </template>
                                                <p class="px-3 py-2 text-xs text-gray-500" x-show="filteredCountries().length === 0">No matches.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                   placeholder="Filled automatically when we know the number">
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
        countries: @json($countries ?? \App\Support\CountryDialCodes::list()),
        countryCode: @json($countryCode ?? old('country_code', '+237')),
        ccQuery: '',
        ccOpen: false,
        phoneLocal: @json(old('phone', '')),
        fullName: @json(old('full_name', '')),
        lookingUp: false,
        statusText: '',
        statusOk: true,
        timer: null,
        boot() {
            if (!this.countryCode || this.countryCode === '237') this.countryCode = '+237';
            if (String(this.countryCode).charAt(0) !== '+') this.countryCode = '+' + this.countryCode;
            if (this.digits(this.phoneLocal).length >= 8) this.lookup();
        },
        digits(v) { return String(v || '').replace(/\D/g, ''); },
        ccLabel() {
            var hit = this.countries.find(function (c) { return c.code === this.countryCode; }.bind(this));
            return hit ? hit.label : this.countryCode;
        },
        filteredCountries() {
            var q = (this.ccQuery || '').trim().toLowerCase();
            if (!q) return this.countries;
            return this.countries.filter(function (c) {
                return (c.label || '').toLowerCase().indexOf(q) !== -1 || (c.code || '').indexOf(q) !== -1;
            });
        },
        selectCountry(c) {
            this.countryCode = c.code;
            this.ccOpen = false;
            this.ccQuery = '';
            this.lookup();
        },
        onPhoneInput() {
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

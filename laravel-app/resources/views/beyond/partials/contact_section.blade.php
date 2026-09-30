@php
    $waPhone = preg_replace('/\D+/', '', \App\Support\SiteContent::text('contact.phone', '+237675321739'));
    $countryCodes = [
        '+237' => 'Cameroon (+237)',
        '+250' => 'Rwanda (+250)',
        '+256' => 'Uganda (+256)',
        '+254' => 'Kenya (+254)',
        '+243' => 'DRC (+243)',
        '+233' => 'Ghana (+233)',
        '+234' => 'Nigeria (+234)',
        '+1' => 'USA/Canada (+1)',
        '+44' => 'UK (+44)',
        '+33' => 'France (+33)',
    ];
@endphp

<section id="contact" class="py-10 sm:py-12 bg-gray-50 scroll-mt-24">
    <div class="max-w-lg mx-auto px-4 sm:px-6"
         x-data="mboleAi()"
         x-init="boot()"
         id="mbole-ai">

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
            {{-- Idle intro (matches Mbole AI card) --}}
            <div x-show="step === 'idle'" class="px-6 pt-8 pb-7 text-center">
                <img src="{{ url('public/branding/mbole-ai.png') }}"
                     alt="Mbole AI"
                     class="mx-auto w-40 h-auto select-none"
                     width="220" height="220" decoding="async">
                <button type="button"
                        @click="startChat()"
                        class="mt-5 w-full max-w-xs mx-auto block rounded-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 text-base transition">
                    Let's Chat
                </button>
            </div>

            {{-- Chat steps --}}
            <div x-show="step !== 'idle'" x-cloak class="flex flex-col min-h-[22rem]">
                <div class="bg-brand-blue text-white px-4 py-3 flex items-center gap-3">
                    <img src="{{ url('public/branding/mbole-ai.png') }}" alt="" class="w-10 h-10 rounded-full object-cover bg-white/10" width="40" height="40">
                    <div class="min-w-0 flex-1">
                        <p class="m-0 font-bold text-sm leading-tight">Mbole AI</p>
                        <p class="m-0 text-[11px] text-blue-100">Beyond Enterprise assistant</p>
                    </div>
                    <button type="button" class="text-blue-100 hover:text-white text-sm font-semibold" @click="reset()">Close</button>
                </div>

                <div class="flex-1 px-4 py-4 space-y-3 bg-slate-50 overflow-y-auto" style="max-height: 22rem;">
                    <div class="flex gap-2 items-end">
                        <img src="{{ url('public/branding/mbole-ai.png') }}" alt="" class="w-7 h-7 rounded-full object-cover shrink-0" width="28" height="28">
                        <div class="rounded-2xl rounded-bl-md bg-white border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm" x-text="botPrompt"></div>
                    </div>

                    <template x-if="resolvedName && step === 'message'">
                        <div class="text-center text-xs text-slate-500" x-text="'Chatting as ' + resolvedName"></div>
                    </template>

                    <div x-show="statusText" x-cloak class="text-xs text-center px-2"
                         :class="statusOk ? 'text-emerald-700' : 'text-amber-700'"
                         x-text="statusText"></div>
                </div>

                <div class="border-t border-slate-200 bg-white p-4 space-y-3">
                    <div x-show="step === 'phone'" x-cloak>
                        <label class="text-xs font-bold text-brand-blue uppercase tracking-wide">WhatsApp number</label>
                        <div class="mt-1 flex gap-2">
                            <select x-model="countryCode" @change="onPhoneChange()"
                                    class="rounded-lg border border-slate-200 px-2 py-2.5 text-sm bg-white w-[7.5rem] shrink-0">
                                @foreach ($countryCodes as $code => $label)
                                    <option value="{{ $code }}">{{ $code }}</option>
                                @endforeach
                            </select>
                            <input type="tel" inputmode="numeric" autocomplete="tel-national"
                                   x-model="phoneLocal" @input="onPhoneChange()"
                                   @keydown.enter.prevent="continueFromPhone()"
                                   placeholder="675321739"
                                   class="flex-1 rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-brand-blue">
                        </div>
                        <button type="button"
                                class="mt-3 w-full rounded-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-2.5 text-sm disabled:opacity-50"
                                :disabled="lookingUp || digits(phoneLocal).length < 8"
                                @click="continueFromPhone()">
                            <span x-show="!lookingUp">Continue</span>
                            <span x-show="lookingUp" x-cloak>Looking up…</span>
                        </button>
                    </div>

                    <div x-show="step === 'name'" x-cloak>
                        <label class="text-xs font-bold text-brand-blue uppercase tracking-wide">Your name</label>
                        <p class="text-xs text-slate-500 mt-0.5 mb-1">We couldn’t resolve this number automatically — please type your name.</p>
                        <input type="text" x-model="fullName" autocomplete="name"
                               @keydown.enter.prevent="continueFromName()"
                               placeholder="Full name"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-brand-blue">
                        <button type="button"
                                class="mt-3 w-full rounded-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-2.5 text-sm disabled:opacity-50"
                                :disabled="!(fullName || '').trim()"
                                @click="continueFromName()">
                            Continue
                        </button>
                    </div>

                    <div x-show="step === 'message'" x-cloak>
                        <label class="text-xs font-bold text-brand-blue uppercase tracking-wide">Your message</label>
                        <textarea x-model="message" rows="3" placeholder="How can we help?"
                                  class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-brand-blue resize-none"></textarea>
                        <button type="button"
                                class="mt-3 w-full rounded-full bg-brand-gold hover:bg-[#c6ab47] text-brand-blue font-extrabold py-2.5 text-sm disabled:opacity-50"
                                :disabled="sending || !(message || '').trim()"
                                @click="submit()">
                            <span x-show="!sending">Send message</span>
                            <span x-show="sending" x-cloak>Sending…</span>
                        </button>
                    </div>

                    <div x-show="step === 'done'" x-cloak class="text-center py-2">
                        <p class="text-sm font-semibold text-brand-blue m-0" x-text="doneMessage"></p>
                        <button type="button" class="mt-3 text-sm font-bold text-brand-blue underline" @click="reset()">Start again</button>
                        <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                           class="mt-2 block text-xs text-slate-500 hover:text-brand-blue">Or open WhatsApp directly</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
function mboleAi() {
    return {
        step: 'idle',
        countryCode: '+237',
        phoneLocal: '',
        fullName: '',
        resolvedName: '',
        message: '',
        lookingUp: false,
        sending: false,
        statusText: '',
        statusOk: true,
        doneMessage: '',
        lookupTimer: null,
        botPrompt: 'Hello! How can I help?',
        boot() {
            var hash = (window.location.hash || '').toLowerCase();
            if (hash === '#contact' || hash === '#mbole-ai') {
                this.$nextTick(function () {
                    document.getElementById('mbole-ai')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
        },
        digits(v) { return String(v || '').replace(/\D/g, ''); },
        isCameroon() { return this.digits(this.countryCode) === '237'; },
        startChat() {
            this.step = 'phone';
            this.botPrompt = 'Share your WhatsApp number — I’ll try to find your name.';
            this.statusText = '';
        },
        reset() {
            this.step = 'idle';
            this.phoneLocal = '';
            this.fullName = '';
            this.resolvedName = '';
            this.message = '';
            this.statusText = '';
            this.botPrompt = 'Hello! How can I help?';
            this.sending = false;
            this.lookingUp = false;
        },
        onPhoneChange() {
            clearTimeout(this.lookupTimer);
            this.statusText = '';
            var self = this;
            this.lookupTimer = setTimeout(function () { self.prefetchLookup(); }, 450);
        },
        prefetchLookup() {
            if (this.digits(this.phoneLocal).length < 8 || !this.isCameroon()) return;
            this.lookupName(true);
        },
        lookupName(silent) {
            var self = this;
            if (this.digits(this.phoneLocal).length < 8) return Promise.resolve(null);
            this.lookingUp = true;
            if (!silent) this.statusText = 'Checking number…';
            var url = @json(route('directory.phone-lookup'))
                + '?phone=' + encodeURIComponent(this.phoneLocal)
                + '&country_code=' + encodeURIComponent(this.countryCode);
            return fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    self.lookingUp = false;
                    var n = (data && (data.original_name || data.name || data.system_name)) || '';
                    if (n) {
                        self.fullName = n;
                        self.resolvedName = n;
                        self.statusOk = true;
                        self.statusText = silent ? '' : 'Found: ' + n;
                    } else {
                        self.statusOk = false;
                        self.statusText = silent ? '' : 'Name not found on mobile money.';
                    }
                    return n;
                })
                .catch(function () {
                    self.lookingUp = false;
                    return '';
                });
        },
        continueFromPhone() {
            if (this.digits(this.phoneLocal).length < 8) return;
            var self = this;
            if (this.isCameroon()) {
                this.lookupName(false).then(function (n) {
                    if (n) {
                        self.goMessage();
                    } else {
                        self.step = 'name';
                        self.botPrompt = 'I couldn’t match that Cameroon number. What’s your name?';
                    }
                });
            } else {
                this.step = 'name';
                this.botPrompt = 'That isn’t a Cameroon number — please tell me your name.';
                this.statusText = '';
            }
        },
        continueFromName() {
            if (!(this.fullName || '').trim()) return;
            this.resolvedName = this.fullName.trim();
            this.goMessage();
        },
        goMessage() {
            this.step = 'message';
            this.botPrompt = 'Thanks' + (this.resolvedName ? ', ' + this.resolvedName.split(' ')[0] : '') + '. What would you like to tell us?';
            this.statusText = '';
        },
        submit() {
            if (!(this.message || '').trim() || this.sending) return;
            var self = this;
            this.sending = true;
            this.statusText = 'Sending via WhatsApp…';
            this.statusOk = true;
            fetch(@json(route('beyond.contact.store')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    country_code: this.countryCode,
                    phone: this.phoneLocal,
                    full_name: this.fullName || this.resolvedName,
                    message: this.message
                })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                  self.sending = false;
                  if (!res.ok || !res.d.ok) {
                      self.statusOk = false;
                      self.statusText = (res.d && res.d.message) || 'Send failed. Try again.';
                      return;
                  }
                  self.doneMessage = res.d.message || 'Sent! Check WhatsApp — an assistant will follow up.';
                  self.step = 'done';
                  self.botPrompt = 'All set. Check your WhatsApp.';
                  self.statusText = '';
              }).catch(function () {
                  self.sending = false;
                  self.statusOk = false;
                  self.statusText = 'Network error. Please try again.';
              });
        }
    };
}
</script>
@endpush

@php
    $mboleCountries = \App\Support\CountryDialCodes::list();
    $mboleStaffWa = preg_replace('/\D+/', '', \App\Support\SiteContent::text('contact.phone', '+237675321739'));
@endphp

<style>
    @keyframes mboleBob {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    @keyframes mboleGlow {
        0%, 100% { box-shadow: 0 8px 24px rgba(0, 61, 130, .35), 0 0 0 0 rgba(212, 175, 55, .45); }
        50% { box-shadow: 0 12px 28px rgba(0, 61, 130, .45), 0 0 0 10px rgba(212, 175, 55, 0); }
    }
    .mbole-fab {
        position: fixed; right: 1.15rem; bottom: 1.15rem; z-index: 95;
        width: 4.5rem; height: 4.5rem; border-radius: 999px;
        border: 3px solid #fff; background: #003D82; padding: 0; overflow: hidden;
        cursor: pointer; animation: mboleBob 2.8s ease-in-out infinite, mboleGlow 2.8s ease-in-out infinite;
    }
    .mbole-fab img { width: 100%; height: 100%; object-fit: cover; object-position: center 12%; display: block; }
    .mbole-fab:hover { transform: scale(1.06); }
    .mbole-panel-bg {
        position: fixed; inset: 0; z-index: 96; background: rgba(15, 23, 42, .45);
    }
    .mbole-panel {
        position: fixed; z-index: 97; right: 1rem; bottom: 5.9rem;
        width: min(22.5rem, calc(100vw - 1.5rem));
        max-height: min(34rem, calc(100dvh - 7rem));
        background: #fff; border-radius: 1.25rem; overflow: hidden;
        box-shadow: 0 20px 50px rgba(15, 23, 42, .28);
        display: flex; flex-direction: column;
        border: 1px solid rgba(212, 175, 55, .4);
    }
    @media (prefers-reduced-motion: reduce) {
        .mbole-fab { animation: none; }
    }
</style>

<div x-data="mboleAiWidget()">
    {{-- Always visible robot — replaces the old WhatsApp bubble on every page --}}
    <button type="button" class="mbole-fab" @click="open = !open" :aria-expanded="open ? 'true' : 'false'" title="Chat with Mbole AI">
        <img src="{{ url('public/branding/mbole-ai.png') }}" alt="Mbole AI" width="72" height="72" decoding="async">
    </button>

    <div x-show="open" x-cloak>
        <div class="mbole-panel-bg" @click="open = false"></div>
        <div class="mbole-panel" role="dialog" aria-label="Mbole AI assistant" @click.stop>
            <div class="bg-brand-blue text-white px-3.5 py-3 flex items-center gap-2.5">
                <img src="{{ url('public/branding/mbole-ai.png') }}" alt="" class="w-11 h-11 rounded-full object-cover bg-white/10 border border-white/20" width="44" height="44">
                <div class="min-w-0 flex-1">
                    <p class="m-0 font-extrabold text-sm leading-tight">Mbole AI</p>
                    <p class="m-0 text-[11px] text-blue-100">Beyond Enterprise assistant</p>
                </div>
                <button type="button" class="text-blue-100 hover:text-white text-sm font-bold" @click="open = false">✕</button>
            </div>

            <div class="flex-1 overflow-y-auto px-3.5 py-3 bg-slate-50 space-y-3" style="min-height: 10rem;">
                <div class="flex gap-2 items-end">
                    <img src="{{ url('public/branding/mbole-ai.png') }}" alt="" class="w-8 h-8 rounded-full object-cover shrink-0" width="32" height="32">
                    <div class="rounded-2xl rounded-bl-md bg-white border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm" x-text="botPrompt"></div>
                </div>
                <p class="text-xs text-center m-0" x-show="statusText"
                   :class="statusOk ? 'text-emerald-700' : 'text-amber-700'" x-text="statusText"></p>
            </div>

            <div class="border-t border-slate-200 bg-white p-3.5 space-y-2.5">
                <div x-show="step === 'idle'">
                    <button type="button" @click="startChat()"
                            class="w-full rounded-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-2.5 text-sm">
                        Let's Chat
                    </button>
                </div>

                <div x-show="step === 'phone'" x-cloak>
                    <label class="text-[10px] font-bold uppercase tracking-wide text-brand-blue">WhatsApp number</label>
                    <div class="mt-1 flex gap-2">
                        <select x-model="countryCode" @change="onPhoneChange()"
                                class="rounded-xl border border-slate-200 px-2 py-2 text-sm font-semibold w-[5.5rem] shrink-0 bg-white">
                            @foreach ($mboleCountries as $c)
                                <option value="{{ $c['code'] }}">{{ $c['code'] }}</option>
                            @endforeach
                        </select>
                        <input type="tel" inputmode="numeric" x-model="phoneLocal" @input="onPhoneChange()"
                               @keydown.enter.prevent="continueFromPhone()"
                               class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-blue min-w-0"
                               placeholder="675321739">
                    </div>
                    <button type="button" class="mt-2 w-full rounded-full bg-brand-blue text-white font-bold py-2 text-sm disabled:opacity-50"
                            :disabled="lookingUp || digits(phoneLocal).length < 8" @click="continueFromPhone()">
                        <span x-text="lookingUp ? 'Looking up…' : 'Continue'"></span>
                    </button>
                </div>

                <div x-show="step === 'name'" x-cloak>
                    <label class="text-[10px] font-bold uppercase tracking-wide text-brand-blue">Your name</label>
                    <input type="text" x-model="fullName" @keydown.enter.prevent="continueFromName()"
                           class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-blue"
                           placeholder="Full name">
                    <button type="button" class="mt-2 w-full rounded-full bg-brand-blue text-white font-bold py-2 text-sm disabled:opacity-50"
                            :disabled="!(fullName || '').trim()" @click="continueFromName()">Continue</button>
                </div>

                <div x-show="step === 'message'" x-cloak>
                    <label class="text-[10px] font-bold uppercase tracking-wide text-brand-blue">Your message</label>
                    <textarea x-model="message" rows="3"
                              class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-blue resize-none"
                              placeholder="How can we help?"></textarea>
                    <button type="button" class="mt-2 w-full rounded-full bg-brand-gold text-brand-blue font-extrabold py-2 text-sm disabled:opacity-50"
                            :disabled="sending || !(message || '').trim()" @click="submit()">
                        <span x-text="sending ? 'Sending…' : 'Send message'"></span>
                    </button>
                </div>

                <div x-show="step === 'done'" x-cloak class="text-center py-1">
                    <p class="text-sm font-semibold text-brand-blue m-0" x-text="doneMessage"></p>
                    <button type="button" class="mt-2 text-sm font-bold text-brand-blue underline" @click="reset()">Start again</button>
                    @if ($mboleStaffWa)
                        <a href="https://wa.me/{{ $mboleStaffWa }}" target="_blank" rel="noopener"
                           class="mt-1 block text-xs text-slate-500 hover:text-brand-blue">Open WhatsApp directly</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mboleAiWidget() {
    return {
        open: false,
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
        init() {
            var hash = (window.location.hash || '').toLowerCase();
            if (hash === '#contact' || hash === '#mbole-ai') {
                this.open = true;
                this.startChat();
            }
            var self = this;
            window.addEventListener('mbole-ai-open', function () {
                self.open = true;
                if (self.step === 'idle') self.startChat();
            });
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
            this.lookupTimer = setTimeout(function () {
                if (self.isCameroon() && self.digits(self.phoneLocal).length >= 8) self.lookupName(true);
            }, 450);
        },
        lookupName(silent) {
            var self = this;
            if (this.digits(this.phoneLocal).length < 8) return Promise.resolve('');
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
                        self.statusText = silent ? '' : ('Found: ' + n);
                    } else {
                        self.statusOk = false;
                        self.statusText = silent ? '' : 'Name not found — please type it.';
                    }
                    return n;
                })
                .catch(function () { self.lookingUp = false; return ''; });
        },
        continueFromPhone() {
            if (this.digits(this.phoneLocal).length < 8) return;
            var self = this;
            if (this.isCameroon()) {
                this.lookupName(false).then(function (n) {
                    if (n) self.goMessage();
                    else {
                        self.step = 'name';
                        self.botPrompt = 'I couldn’t match that number. What’s your name?';
                    }
                });
            } else {
                this.step = 'name';
                this.botPrompt = 'Please tell me your name.';
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
            var first = (this.resolvedName || '').split(' ')[0];
            this.botPrompt = 'Thanks' + (first ? ', ' + first : '') + '. What would you like to tell us?';
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
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '{{ csrf_token() }}'
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

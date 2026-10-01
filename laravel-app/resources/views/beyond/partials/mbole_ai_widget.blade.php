{{-- Mbole AI — Website Beyond Assistant (poll-based chat) --}}
<style>
#mbole-ai-root{position:fixed;right:24px;bottom:24px;z-index:99990;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif}
#mbole-ai-root *{box-sizing:border-box}
.mbole-fab{width:92px;height:104px;border-radius:18px;border:0;padding:4px;cursor:pointer;background:transparent;position:relative;box-shadow:none;animation:mbole-bob 2.8s ease-in-out infinite}
.mbole-fab img{width:100%;height:100%;border-radius:0;display:block;object-fit:contain;object-position:center bottom;background:transparent;filter:drop-shadow(0 10px 18px rgba(11,61,145,.35))}
.mbole-fab::after{content:"";position:absolute;left:50%;bottom:2px;width:48px;height:10px;margin-left:-24px;border-radius:50%;background:radial-gradient(ellipse,rgba(11,61,145,.28),transparent 70%);animation:mbole-glow 2.8s ease-in-out infinite;pointer-events:none}
.mbole-greet{position:absolute;right:108px;bottom:28px;max-width:230px;background:#fff;color:#0f172a;border-radius:14px;padding:12px 14px;box-shadow:0 12px 32px rgba(15,23,42,.18);font-size:13px;line-height:1.4}
.mbole-greet strong{display:block;margin-bottom:4px;font-size:13px}
.mbole-greet button{margin-top:8px;border:0;background:#0b3d91;color:#fff;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer}
.mbole-panel{display:none;width:min(380px,calc(100vw - 24px));height:auto;max-height:min(560px,calc(100vh - 48px));background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 22px 60px rgba(15,23,42,.28);flex-direction:column}
.mbole-panel.open{display:flex!important}
.mbole-panel.is-chat{height:min(560px,calc(100vh - 48px))}
#mbole-ai-root.is-open .mbole-fab{visibility:hidden;pointer-events:none}
#mbole-ai-root.is-open .mbole-greet{display:none!important}
.mbole-head{display:flex;align-items:center;gap:10px;padding:12px 14px;background:linear-gradient(135deg,#0b3d91,#1d4ed8);color:#fff}
.mbole-head img{width:44px;height:48px;border-radius:10px;object-fit:contain;object-position:center;background:rgba(255,255,255,.12)}
.mbole-head .meta{flex:1;min-width:0}
.mbole-head .meta strong{display:block;font-size:14px;line-height:1.2}
.mbole-head .meta span{display:block;font-size:11px;opacity:.9}
.mbole-head .online{font-size:11px;opacity:.85}
.mbole-head button{border:0;background:transparent;color:#fff;font-size:18px;line-height:1;cursor:pointer;padding:4px 6px}
.mbole-thread{flex:1;overflow:auto;padding:14px;background:#f8fafc;display:flex;flex-direction:column;gap:10px}
.mbole-msg{max-width:92%;padding:10px 12px;border-radius:14px;font-size:13px;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.mbole-msg.assistant,.mbole-msg.staff{align-self:flex-start;background:#fff;border:1px solid #e2e8f0;color:#0f172a;border-bottom-left-radius:4px}
.mbole-msg.visitor{align-self:flex-end;background:#0b3d91;color:#fff;border-bottom-right-radius:4px}
.mbole-msg.staff{border-left:3px solid #f59e0b}
.mbole-msg.has-choices{white-space:normal;max-width:100%;width:100%}
.mbole-msg-text{white-space:pre-wrap}
.mbole-choices{display:flex;flex-direction:column;gap:6px;margin-top:10px}
.mbole-choice{
  display:flex;align-items:center;gap:10px;width:100%;border:1px solid #cbd5e1;border-radius:12px;
  padding:9px 12px;background:#fff;color:#0b3d91;font-size:13px;font-weight:650;cursor:pointer;text-align:left
}
.mbole-choice:hover{border-color:#0b3d91;background:#eff6ff}
.mbole-choice .radio{
  width:16px;height:16px;border:2px solid #0b3d91;border-radius:50%;flex-shrink:0;position:relative;background:#fff
}
.mbole-choice.is-on .radio::after{
  content:"";position:absolute;inset:3px;border-radius:50%;background:#0b3d91
}
.mbole-choices.is-check .mbole-choice .radio{border-radius:4px}
.mbole-choices.is-check .mbole-choice.is-on .radio::after{border-radius:2px;inset:3px}
.mbole-choice-confirm{
  margin-top:8px;width:100%;border:0;border-radius:12px;padding:10px 14px;background:#0b3d91;color:#fff;
  font-size:13px;font-weight:700;cursor:pointer
}
.mbole-choice-confirm:disabled{opacity:.55;cursor:default}
.mbole-choice:disabled,.mbole-choice.is-used{opacity:.55;cursor:default;pointer-events:none}
.mbole-typing{align-self:flex-start;font-size:12px;color:#64748b;display:none;padding:0 14px 8px}
.mbole-typing.on{display:block}
.mbole-compose{display:flex;gap:8px;padding:10px;border-top:1px solid #e2e8f0;background:#fff}
.mbole-compose input{flex:1;border:1px solid #cbd5e1;border-radius:999px;padding:10px 14px;font-size:13px;outline:none}
.mbole-compose button{border:0;background:#0b3d91;color:#fff;border-radius:999px;padding:0 16px;font-weight:600;cursor:pointer}
.mbole-footer{padding:0 12px 10px;background:#fff;display:flex;justify-content:space-between;align-items:center}
.mbole-footer a{font-size:11px;color:#0b3d91;text-decoration:none}
.mbole-footer button{border:0;background:transparent;color:#64748b;font-size:11px;cursor:pointer}
.mbole-gate{flex:0 0 auto;overflow:visible;padding:16px 16px 18px;background:#fff;display:none}
.mbole-gate.on{display:block}
.mbole-gate-title{display:flex;align-items:center;gap:8px;margin:0 0 6px;color:#0b3d91;font-size:16px;font-weight:800}
.mbole-gate-title svg{width:18px;height:18px;flex-shrink:0;color:#c9a227}
.mbole-gate-sub{margin:0 0 14px;color:#64748b;font-size:13px;line-height:1.4}
.mbole-gate label{display:block;margin:0 0 6px;color:#0b3d91;font-size:13px;font-weight:700}
.mbole-gate-row{display:flex;gap:8px;align-items:stretch;position:relative}
.mbole-gate-row input[type="tel"],.mbole-gate-row input[type="text"]{
  border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font-size:14px;color:#0f172a;background:#fff;outline:none;min-width:0
}
.mbole-gate-row input{flex:1}
.mbole-gate-row input:focus{border-color:#0b3d91;box-shadow:0 0 0 3px rgba(11,61,145,.12)}
.mbole-cc{position:relative;width:48%;flex-shrink:0}
.mbole-cc-btn{
  width:100%;display:flex;align-items:center;justify-content:space-between;gap:6px;
  border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;font-size:13px;font-weight:700;
  color:#0b3d91;background:#fff;cursor:pointer;text-align:left;min-height:44px
}
.mbole-cc-btn:focus,.mbole-cc.open .mbole-cc-btn{border-color:#0b3d91;box-shadow:0 0 0 3px rgba(11,61,145,.12);outline:none}
.mbole-cc-btn span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mbole-cc-btn svg{width:14px;height:14px;flex-shrink:0;opacity:.7}
.mbole-cc-menu{
  display:none;position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:20;
  background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 16px 40px rgba(15,23,42,.18);
  max-height:260px;overflow:hidden;flex-direction:column
}
.mbole-cc.open .mbole-cc-menu{display:flex}
.mbole-cc-search{margin:8px;border:1px solid #cbd5e1;border-radius:8px;padding:9px 10px;font-size:13px;outline:none}
.mbole-cc-search:focus{border-color:#0b3d91}
.mbole-cc-list{overflow:auto;flex:1;padding:0 4px 6px}
.mbole-cc-item{
  display:block;width:100%;text-align:left;border:0;background:transparent;border-radius:8px;
  padding:9px 10px;font-size:13px;color:#0f172a;cursor:pointer
}
.mbole-cc-item:hover,.mbole-cc-item.is-active{background:#eff6ff;color:#0b3d91;font-weight:600}
.mbole-cc-empty{padding:10px;font-size:12px;color:#94a3b8;text-align:center}
.mbole-gate-hint{margin:8px 0 0;color:#94a3b8;font-size:12px}
.mbole-gate-err{margin:10px 0 0;color:#b91c1c;font-size:12px;display:none}
.mbole-gate-err.on{display:block}
.mbole-gate-go{margin-top:16px;width:100%;border:0;background:#0b3d91;color:#fff;border-radius:999px;padding:12px 16px;font-size:14px;font-weight:700;cursor:pointer}
.mbole-gate-go:disabled{opacity:.65;cursor:wait}
.mbole-chat-wrap{flex:1;min-height:0;display:none;flex-direction:column}
.mbole-chat-wrap.on{display:flex}
@keyframes mbole-bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@keyframes mbole-glow{0%,100%{opacity:.35;transform:scale(1)}50%{opacity:.8;transform:scale(1.04)}}
@media (max-width:640px){
  #mbole-ai-root{right:max(12px,env(safe-area-inset-right));bottom:max(12px,env(safe-area-inset-bottom))}
  .mbole-fab{width:72px;height:82px}
  .mbole-greet{right:auto;left:12px;bottom:96px;max-width:min(240px,calc(100vw - 110px))}
  .mbole-panel,.mbole-panel.is-chat{
    position:fixed;inset:0;width:100%;height:100%;max-height:100%;
    height:100dvh;max-height:100dvh;border-radius:0;
    padding-bottom:env(safe-area-inset-bottom)
  }
  .mbole-head{padding-top:max(12px,env(safe-area-inset-top))}
  .mbole-compose input,.mbole-gate-row input{font-size:16px}
  .mbole-choice,.mbole-choice-confirm,.mbole-compose button{min-height:44px}
  .mbole-gate-row{flex-direction:column}
  .mbole-cc{width:100%}
}
</style>

<div id="mbole-ai-root" aria-live="polite">
  <div class="mbole-greet" id="mbole-greet" style="display:none" hidden>
    <strong id="mbole-greet-title">Hello! How can I help?</strong>
    <button type="button" id="mbole-greet-cta">Let's Chat</button>
  </div>
  <button type="button" class="mbole-fab" id="mbole-fab" aria-label="Open Mbole AI chat">
    <img src="{{ asset('branding/mbole-ai.png') }}?v=2" alt="Mbole AI" width="92" height="104">
  </button>
  <div class="mbole-panel" id="mbole-panel" role="dialog" aria-label="Mbole AI chat" aria-hidden="true">
    <div class="mbole-head">
      <img src="{{ asset('branding/mbole-ai.png') }}?v=2" alt="">
      <div class="meta">
        <strong id="mbole-name">Mbole AI</strong>
        <span>BeyondTechWorld Assistant</span>
        <span class="online">Online</span>
      </div>
      <button type="button" id="mbole-min" title="Minimize" aria-label="Minimize">–</button>
      <button type="button" id="mbole-close" title="Close" aria-label="Close">×</button>
    </div>

    <div class="mbole-gate on" id="mbole-phone-gate">
      <h3 class="mbole-gate-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        Phone Number
      </h3>
      <p class="mbole-gate-sub">Pick a Country and put the phone number.</p>
      <label for="mbole-phone-local">Phone number *</label>
      <div class="mbole-gate-row">
        <div class="mbole-cc" id="mbole-cc">
          <input type="hidden" id="mbole-country" value="+237">
          <button type="button" class="mbole-cc-btn" id="mbole-cc-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Country code">
            <span id="mbole-cc-label">Cameroon (+237)</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="mbole-cc-menu" id="mbole-cc-menu" role="listbox" hidden>
            <input type="search" class="mbole-cc-search" id="mbole-cc-search" placeholder="Search country or +code" autocomplete="off" enterkeyhint="search" inputmode="search" aria-label="Search country">
            <div class="mbole-cc-list" id="mbole-cc-list"></div>
          </div>
        </div>
        <input type="tel" id="mbole-phone-local" inputmode="numeric" autocomplete="tel-national" placeholder="National number" maxlength="15" aria-label="Phone number">
      </div>
      <p class="mbole-gate-hint">Do not type a country code in this box.</p>
      <p class="mbole-gate-err" id="mbole-phone-err"></p>
      <button type="button" class="mbole-gate-go" id="mbole-phone-go">Continue</button>
    </div>

    <div class="mbole-gate" id="mbole-otp-gate">
      <h3 class="mbole-gate-title">Verify your number</h3>
      <p class="mbole-gate-sub">A 6-digit code was sent to your WhatsApp. Type it here so we know this is you.</p>
      <label for="mbole-otp-local">Verification code *</label>
      <div class="mbole-gate-row">
        <input type="text" id="mbole-otp-local" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" maxlength="6" aria-label="Verification code" style="flex:1;width:100%">
      </div>
      <p class="mbole-gate-err" id="mbole-otp-err"></p>
      <button type="button" class="mbole-gate-go" id="mbole-otp-go">Verify</button>
    </div>

    <div class="mbole-gate" id="mbole-name-gate">
      <h3 class="mbole-gate-title">Your name</h3>
      <p class="mbole-gate-sub">We couldn’t find a name for that number. What should I call you?</p>
      <label for="mbole-name-local">Full name *</label>
      <div class="mbole-gate-row">
        <input type="text" id="mbole-name-local" autocomplete="name" placeholder="Your name" maxlength="80" aria-label="Your name" style="flex:1;width:100%">
      </div>
      <p class="mbole-gate-err" id="mbole-name-err"></p>
      <button type="button" class="mbole-gate-go" id="mbole-name-go">Continue</button>
    </div>

    <div class="mbole-chat-wrap" id="mbole-chat-wrap">
      <div class="mbole-thread" id="mbole-thread"></div>
      <div class="mbole-typing" id="mbole-typing">Mbole is typing…</div>
      <form class="mbole-compose" id="mbole-form" autocomplete="off">
        <input type="text" id="mbole-input" maxlength="2000" placeholder="Type your message…" aria-label="Message">
        <button type="submit">Send</button>
      </form>
      <div class="mbole-footer">
        <a id="mbole-wa" href="#" target="_blank" rel="noopener" style="display:none">Continue on WhatsApp</a>
        <button type="button" id="mbole-dismiss">Don't show greeting</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var TOKEN_KEY = 'mbole_ai_token';
  var DISMISS_KEY = 'mbole_ai_greet_dismissed';
  var OPENED_KEY = 'mbole_ai_opened_once';
  var LAST_ACTIVE_KEY = 'mbole_ai_last_active';
  var IDLE_MS = 5 * 60 * 1000;
  var CSRF = document.querySelector('meta[name="csrf-token"]');
  var csrf = CSRF ? CSRF.getAttribute('content') : '';
  var token = localStorage.getItem(TOKEN_KEY) || '';
  var cursor = 0;
  var pollTimer = null;
  var idleTimer = null;
  var sending = false;
  var onboarding = 'need_phone';
  var cfg = { enabled: true, name: 'Mbole AI', greeting: 'Hello! How can I help?', greeting_delay_ms: 600, continue_whatsapp: true };
  var countries = @json(\App\Support\CountryDialCodes::list());
  var countryCode = '+237';

  var fab = document.getElementById('mbole-fab');
  var panel = document.getElementById('mbole-panel');
  var greet = document.getElementById('mbole-greet');
  var thread = document.getElementById('mbole-thread');
  var form = document.getElementById('mbole-form');
  var input = document.getElementById('mbole-input');
  var typing = document.getElementById('mbole-typing');
  var waLink = document.getElementById('mbole-wa');
  var phoneGate = document.getElementById('mbole-phone-gate');
  var otpGate = document.getElementById('mbole-otp-gate');
  var nameGate = document.getElementById('mbole-name-gate');
  var chatWrap = document.getElementById('mbole-chat-wrap');
  var countryEl = document.getElementById('mbole-country');
  var phoneLocal = document.getElementById('mbole-phone-local');
  var phoneErr = document.getElementById('mbole-phone-err');
  var phoneGo = document.getElementById('mbole-phone-go');
  var otpLocal = document.getElementById('mbole-otp-local');
  var otpErr = document.getElementById('mbole-otp-err');
  var otpGo = document.getElementById('mbole-otp-go');
  var nameLocal = document.getElementById('mbole-name-local');
  var nameErr = document.getElementById('mbole-name-err');
  var nameGo = document.getElementById('mbole-name-go');
  var ccRoot = document.getElementById('mbole-cc');
  var ccBtn = document.getElementById('mbole-cc-btn');
  var ccMenu = document.getElementById('mbole-cc-menu');
  var ccSearch = document.getElementById('mbole-cc-search');
  var ccList = document.getElementById('mbole-cc-list');
  var ccLabel = document.getElementById('mbole-cc-label');

  function nowMs() { return Date.now(); }

  function touchActivity() {
    try { localStorage.setItem(LAST_ACTIVE_KEY, String(nowMs())); } catch (e) {}
    scheduleIdleCheck();
  }

  function lastActivityMs() {
    var raw = 0;
    try { raw = parseInt(localStorage.getItem(LAST_ACTIVE_KEY) || '0', 10) || 0; } catch (e) { raw = 0; }
    return raw;
  }

  function isIdleExpired() {
    var last = lastActivityMs();
    if (!last) return false;
    return (nowMs() - last) >= IDLE_MS;
  }

  function scheduleIdleCheck() {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(function () {
      if (isIdleExpired()) {
        startFreshChat('idle');
      } else {
        scheduleIdleCheck();
      }
    }, Math.min(30000, IDLE_MS));
  }

  function startFreshChat(reason) {
    stopPoll();
    if (idleTimer) { clearTimeout(idleTimer); idleTimer = null; }
    token = '';
    cursor = 0;
    sending = false;
    typing.classList.remove('on');
    try {
      localStorage.removeItem(TOKEN_KEY);
      localStorage.setItem(LAST_ACTIVE_KEY, String(nowMs()));
    } catch (e) {}
    thread.innerHTML = '';
    if (phoneLocal) phoneLocal.value = '';
    if (nameLocal) nameLocal.value = '';
    if (input) input.value = '';
    showErr(phoneErr, '');
    showErr(nameErr, '');
    applyOnboarding('need_phone');
    if (reason === 'idle' && panel.classList.contains('open')) {
      appendMsg({
        id: 'idle-' + nowMs(),
        role: 'assistant',
        body: 'This chat ended after 5 minutes of inactivity. Starting a new conversation — please confirm your phone number to continue.'
      });
    }
    scheduleIdleCheck();
  }

  function confirmEndChat() {
    var ok = window.confirm('Closing will end this chat. Are you sure you want to end the conversation?');
    if (!ok) return false;
    startFreshChat('close');
    setOpen(false);
    return true;
  }

  function countryLabel(code) {
    for (var i = 0; i < countries.length; i++) {
      if (countries[i].code === code) return countries[i].label;
    }
    return code;
  }

  function setCountry(code) {
    countryCode = code || '+237';
    countryEl.value = countryCode;
    ccLabel.textContent = countryLabel(countryCode);
  }

  function closeCc() {
    ccRoot.classList.remove('open');
    ccMenu.hidden = true;
    ccBtn.setAttribute('aria-expanded', 'false');
    ccSearch.value = '';
  }

  function openCc() {
    ccRoot.classList.add('open');
    ccMenu.hidden = false;
    ccBtn.setAttribute('aria-expanded', 'true');
    renderCcList(ccSearch.value);
    setTimeout(function () { try { ccSearch.focus(); ccSearch.select(); } catch (e) {} }, 30);
  }

  function renderCcList(query) {
    var q = String(query || '').trim().toLowerCase();
    ccList.innerHTML = '';
    var matches = countries.filter(function (c) {
      if (!q) return true;
      return (c.label + ' ' + c.code).toLowerCase().indexOf(q) !== -1;
    });
    if (!matches.length) {
      var empty = document.createElement('div');
      empty.className = 'mbole-cc-empty';
      empty.textContent = 'No matches.';
      ccList.appendChild(empty);
      return;
    }
    matches.forEach(function (c) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mbole-cc-item' + (c.code === countryCode ? ' is-active' : '');
      btn.setAttribute('role', 'option');
      btn.setAttribute('aria-selected', c.code === countryCode ? 'true' : 'false');
      btn.textContent = c.label;
      btn.addEventListener('click', function () {
        setCountry(c.code);
        closeCc();
        try { phoneLocal.focus(); } catch (e) {}
      });
      ccList.appendChild(btn);
    });
  }

  ccBtn.addEventListener('click', function (e) {
    e.preventDefault();
    if (ccRoot.classList.contains('open')) closeCc();
    else openCc();
  });
  ccSearch.addEventListener('input', function () { renderCcList(ccSearch.value); });
  ccSearch.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.preventDefault(); closeCc(); }
    if (e.key === 'Enter') {
      e.preventDefault();
      var first = ccList.querySelector('.mbole-cc-item');
      if (first) first.click();
    }
  });
  document.addEventListener('click', function (e) {
    if (!ccRoot.contains(e.target)) closeCc();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ccRoot.classList.contains('open')) closeCc();
  });
  setCountry('+237');
  renderCcList('');

  function applyOnboarding(step) {
    if (step) onboarding = step;
    phoneGate.classList.toggle('on', onboarding === 'need_phone');
    otpGate.classList.toggle('on', onboarding === 'need_otp');
    nameGate.classList.toggle('on', onboarding === 'need_name');
    chatWrap.classList.toggle('on', onboarding === 'ready');
    panel.classList.toggle('is-chat', onboarding === 'ready');
    if (onboarding === 'need_phone') {
      setTimeout(function () { try { phoneLocal.focus(); } catch (e) {} }, 60);
    } else if (onboarding === 'need_otp') {
      setTimeout(function () { try { otpLocal.focus(); } catch (e) {} }, 60);
    } else if (onboarding === 'need_name') {
      setTimeout(function () { try { nameLocal.focus(); } catch (e) {} }, 60);
    } else {
      setTimeout(function () { try { input.focus(); } catch (e) {} }, 60);
    }
  }

  function showErr(el, msg) {
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.classList.add('on');
    } else {
      el.textContent = '';
      el.classList.remove('on');
    }
  }

  function digitsOnly(v) {
    return String(v || '').replace(/\D/g, '');
  }

  function combinePhone(code, local) {
    var codeDigits = digitsOnly(code);
    var localDigits = digitsOnly(local);
    if (localDigits.charAt(0) === '0') localDigits = localDigits.replace(/^0+/, '');
    if (codeDigits && localDigits.indexOf(codeDigits) === 0 && localDigits.length - codeDigits.length >= 7) {
      localDigits = localDigits.slice(codeDigits.length);
    }
    return '+' + codeDigits + localDigits;
  }

  function api(path, opts) {
    opts = opts || {};
    var headers = opts.headers || {};
    headers['Accept'] = 'application/json';
    headers['X-Requested-With'] = 'XMLHttpRequest';
    headers['X-Page-Path'] = location.pathname;
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
    if (opts.json) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.json);
      delete opts.json;
    }
    opts.headers = headers;
    opts.credentials = 'same-origin';
    return fetch(path, opts).then(function (r) {
      return r.text().then(function (text) {
        var j = {};
        try { j = text ? JSON.parse(text) : {}; } catch (e) { j = { error: text || ('HTTP ' + r.status) }; }
        return { ok: r.ok, status: r.status, body: j };
      });
    });
  }

  function lockAllChoiceGroups() {
    thread.querySelectorAll('.mbole-choices:not(.is-locked)').forEach(function (wrap) {
      wrap.classList.add('is-locked');
      wrap.querySelectorAll('.mbole-choice, .mbole-choice-confirm').forEach(function (b) {
        b.disabled = true;
        b.classList.add('is-used');
      });
    });
  }

  function appendMsg(msg) {
    if (!msg || !msg.id) return;
    if (thread.querySelector('[data-id="' + msg.id + '"]')) return;
    if (msg.role === 'assistant') {
      lockAllChoiceGroups();
    }
    var el = document.createElement('div');
    var hasChoices = msg.role === 'assistant' && msg.choices && msg.choices.length;
    // Never render the obsolete LED Screen / No screen pair (stuck UI from older turns).
    if (hasChoices) {
      var vals = msg.choices.map(function (c) { return String(c.value || '').toLowerCase(); });
      if (vals.indexOf('screen:yes') !== -1 && vals.indexOf('screen:no') !== -1) {
        hasChoices = false;
        msg.choices = null;
      }
    }
    var multi = hasChoices && (msg.choice_mode === 'checkbox' || (msg.ui && msg.ui.mode === 'checkbox'));
    el.className = 'mbole-msg ' + (msg.role || 'assistant') + (hasChoices ? ' has-choices' : '');
    el.setAttribute('data-id', msg.id);
    if (hasChoices) {
      var text = document.createElement('div');
      text.className = 'mbole-msg-text';
      text.textContent = msg.body || '';
      el.appendChild(text);
      var wrap = document.createElement('div');
      wrap.className = 'mbole-choices' + (multi ? ' is-check' : '');
      wrap.setAttribute('role', multi ? 'group' : 'radiogroup');
      wrap.setAttribute('aria-label', multi ? 'Select all that apply' : 'Choose an option');
      msg.choices.forEach(function (choice) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mbole-choice';
        btn.setAttribute('role', multi ? 'checkbox' : 'radio');
        btn.setAttribute('aria-checked', 'false');
        btn.setAttribute('data-value', choice.value || choice.label || '');
        btn.innerHTML = '<span class="radio" aria-hidden="true"></span><span></span>';
        btn.querySelector('span:last-child').textContent = choice.label || choice.value || '';
        btn.addEventListener('click', function () {
          if (sending || wrap.classList.contains('is-locked')) return;
          if (multi) {
            var val = (btn.getAttribute('data-value') || '').toLowerCase();
            if (val === 'none') {
              wrap.querySelectorAll('.mbole-choice').forEach(function (b) {
                b.classList.remove('is-on');
                b.setAttribute('aria-checked', 'false');
              });
              btn.classList.add('is-on');
              btn.setAttribute('aria-checked', 'true');
            } else {
              var noneBtn = wrap.querySelector('.mbole-choice[data-value="none"]');
              if (noneBtn) {
                noneBtn.classList.remove('is-on');
                noneBtn.setAttribute('aria-checked', 'false');
              }
              var on = btn.classList.toggle('is-on');
              btn.setAttribute('aria-checked', on ? 'true' : 'false');
            }
            return;
          }
          wrap.querySelectorAll('.mbole-choice').forEach(function (b) {
            b.classList.remove('is-on');
            b.setAttribute('aria-checked', 'false');
            b.disabled = true;
            b.classList.add('is-used');
          });
          btn.classList.add('is-on');
          btn.setAttribute('aria-checked', 'true');
          var value = btn.getAttribute('data-value') || '';
          var label = (choice.label || value);
          sendChatMessage(value || label);
        });
        wrap.appendChild(btn);
      });
      if (multi) {
        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'mbole-choice-confirm';
        confirm.textContent = (msg.confirm_label || (msg.ui && msg.ui.confirm_label) || 'Continue');
        confirm.addEventListener('click', function () {
          if (sending || wrap.classList.contains('is-locked')) return;
          var selected = [];
          wrap.querySelectorAll('.mbole-choice.is-on').forEach(function (b) {
            selected.push(b.getAttribute('data-value') || '');
          });
          if (!selected.length) return;
          wrap.classList.add('is-locked');
          wrap.querySelectorAll('.mbole-choice').forEach(function (b) {
            b.disabled = true;
            b.classList.add('is-used');
          });
          confirm.disabled = true;
          var payload = selected.indexOf('none') !== -1
            ? 'extras:none'
            : ('extras:' + selected.filter(function (s) { return s && s !== 'none'; }).join(','));
          sendChatMessage(payload);
        });
        wrap.appendChild(confirm);
      }
      el.appendChild(wrap);
    } else {
      el.textContent = msg.body || '';
    }
    thread.appendChild(el);
    thread.scrollTop = thread.scrollHeight;
    if (msg.id > cursor) cursor = msg.id;
  }

  function sendChatMessage(body) {
    body = String(body || '').trim();
    if (!body || sending || onboarding !== 'ready') return Promise.resolve();
    touchActivity();
    sending = true;
    typing.classList.add('on');
    return ensureSession().then(function () {
      return api('/api/website-chat/messages', {
        method: 'POST',
        json: { token: token, body: body, path: location.pathname }
      });
    }).then(function (res) {
      typing.classList.remove('on');
      sending = false;
      touchActivity();
      if (!res.ok || !res.body.success) {
        appendMsg({ id: 'err-' + Date.now(), role: 'assistant', body: (res.body && (res.body.error || res.body.message)) || 'Sorry, something went wrong.' });
        return;
      }
      applyOnboarding(res.body.onboarding || onboarding);
      (res.body.messages || []).forEach(appendMsg);
    }).catch(function (err) {
      typing.classList.remove('on');
      sending = false;
      appendMsg({ id: 'err-' + Date.now(), role: 'assistant', body: (err && err.message) || 'Network error. Please try again.' });
    });
  }

  function setOpen(open) {
    var root = document.getElementById('mbole-ai-root');
    panel.classList.toggle('open', !!open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (root) root.classList.toggle('is-open', !!open);
    greet.hidden = true;
    greet.style.display = 'none';
    if (open) {
      localStorage.setItem(OPENED_KEY, '1');
      if (isIdleExpired()) {
        startFreshChat('idle');
      }
      touchActivity();
      ensureSession().then(function (body) {
        applyOnboarding(body.onboarding || onboarding);
        if (onboarding === 'ready') {
          return poll(true).then(function () { startPoll(); });
        }
        stopPoll();
      }).catch(function (err) {
        showErr(phoneErr, (err && err.message) || 'Could not start chat. Please refresh and try again.');
        applyOnboarding('need_phone');
      });
    } else {
      stopPoll();
    }
  }

  function ensureSession() {
    return api('/api/website-chat/session', {
      method: 'POST',
      json: { token: token || null, path: location.pathname }
    }).then(function (res) {
      if (!res.ok || !res.body.success) throw new Error((res.body && (res.body.error || res.body.message)) || 'Could not start chat.');
      token = res.body.token;
      localStorage.setItem(TOKEN_KEY, token);
      if (res.body.assistant_name) {
        document.getElementById('mbole-name').textContent = res.body.assistant_name;
      }
      if (res.body.continue_whatsapp && cfg.continue_whatsapp) {
        waLink.style.display = '';
        waLink.href = 'https://wa.me/{{ preg_replace("/\\D/", "", config("services.whatsapp.public_number", config("services.whatsapp.business_number", "237650000000"))) }}?text=' + encodeURIComponent('Hi BeyondTechWorld — continuing my website chat (ref BTW-WEB-' + token.slice(0, 8) + ').');
      }
      return res.body;
    });
  }

  function poll(initial) {
    if (!token || onboarding !== 'ready') return Promise.resolve();
    var url = '/api/website-chat/messages?token=' + encodeURIComponent(token) + '&after=' + encodeURIComponent(cursor);
    return api(url).then(function (res) {
      if (!res.ok || !res.body.success) return;
      applyOnboarding(res.body.onboarding || onboarding);
      if (onboarding === 'ready') {
        (res.body.messages || []).forEach(appendMsg);
      }
    }).catch(function () {});
  }

  function startPoll() {
    stopPoll();
    pollTimer = setInterval(function () { poll(false); }, 4000);
  }
  function stopPoll() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
  }

  function sendBody(body, goBtn, errEl) {
    if (sending) return Promise.resolve();
    touchActivity();
    sending = true;
    if (goBtn) goBtn.disabled = true;
    typing.classList.add('on');
    return ensureSession().then(function () {
      return api('/api/website-chat/messages', {
        method: 'POST',
        json: { token: token, body: body, path: location.pathname }
      });
    }).then(function (res) {
      typing.classList.remove('on');
      sending = false;
      if (goBtn) goBtn.disabled = false;
      touchActivity();
      if (res.body && res.body.onboarding) applyOnboarding(res.body.onboarding);
      if (!res.ok || !res.body.success) {
        showErr(errEl, (res.body && (res.body.error || res.body.message)) || 'Sorry, something went wrong.');
        return res;
      }
      showErr(errEl, '');
      applyOnboarding(res.body.onboarding || onboarding);
      if (onboarding === 'ready') {
        (res.body.messages || []).forEach(function (m) {
          // Skip echoing the raw phone / name the visitor just typed in the gate.
          if (m.role === 'visitor') return;
          appendMsg(m);
        });
        startPoll();
      }
      return res;
    }).catch(function (err) {
      typing.classList.remove('on');
      sending = false;
      if (goBtn) goBtn.disabled = false;
      showErr(errEl, (err && err.message) || 'Network error. Please try again.');
    });
  }

  phoneGo.addEventListener('click', function () {
    showErr(phoneErr, '');
    var local = digitsOnly(phoneLocal.value);
    if (local.length < 8) {
      showErr(phoneErr, 'Enter a valid national phone number.');
      phoneLocal.focus();
      return;
    }
    sendBody(combinePhone(countryCode || countryEl.value, phoneLocal.value), phoneGo, phoneErr);
  });
  phoneLocal.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); phoneGo.click(); }
  });

  otpGo.addEventListener('click', function () {
    showErr(otpErr, '');
    var code = digitsOnly(otpLocal.value);
    if (code.length !== 6) {
      showErr(otpErr, 'Enter the 6-digit code from WhatsApp.');
      otpLocal.focus();
      return;
    }
    sendBody(code, otpGo, otpErr);
  });
  otpLocal.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); otpGo.click(); }
  });

  nameGo.addEventListener('click', function () {
    showErr(nameErr, '');
    var name = (nameLocal.value || '').trim();
    if (name.length < 2) {
      showErr(nameErr, 'Please enter your name.');
      nameLocal.focus();
      return;
    }
    sendBody(name, nameGo, nameErr);
  });
  nameLocal.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); nameGo.click(); }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending || onboarding !== 'ready') return;
    var body = (input.value || '').trim();
    if (!body) return;
    input.value = '';
    sendChatMessage(body);
  });

  // Any interaction inside the open panel counts as activity.
  panel.addEventListener('click', function () { touchActivity(); });
  panel.addEventListener('keydown', function () { touchActivity(); });
  if (input) {
    input.addEventListener('input', function () { touchActivity(); });
    input.addEventListener('focus', function () { touchActivity(); });
  }

  fab.addEventListener('click', function (e) { e.preventDefault(); setOpen(true); });
  document.getElementById('mbole-greet-cta').addEventListener('click', function (e) { e.preventDefault(); setOpen(true); });
  document.getElementById('mbole-close').addEventListener('click', function (e) {
    e.preventDefault();
    confirmEndChat();
  });
  document.getElementById('mbole-min').addEventListener('click', function () {
    setOpen(false);
    if (token) api('/api/website-chat/minimize', { method: 'POST', json: { token: token } });
  });
  document.getElementById('mbole-dismiss').addEventListener('click', function () {
    localStorage.setItem(DISMISS_KEY, '1');
    greet.hidden = true;
    greet.style.display = 'none';
  });
  window.addEventListener('mbole-ai-open', function () { setOpen(true); });

  // Expire stale sessions from a previous visit before wiring onboarding UI.
  if (token && isIdleExpired()) {
    startFreshChat('idle');
  } else {
    applyOnboarding(onboarding);
    if (token) touchActivity();
    else scheduleIdleCheck();
  }

  api('/api/website-chat/config').then(function (res) {
    if (!res.ok || !res.body.success) return;
    cfg = Object.assign(cfg, res.body);
    if (!cfg.enabled) {
      document.getElementById('mbole-ai-root').style.display = 'none';
      return;
    }
    if (cfg.name) document.getElementById('mbole-name').textContent = cfg.name;
    greet.hidden = true;
    greet.style.display = 'none';
    if (localStorage.getItem(TOKEN_KEY)) {
      token = localStorage.getItem(TOKEN_KEY);
      if (isIdleExpired()) startFreshChat('idle');
    }
  }).catch(function () {});
})();
</script>

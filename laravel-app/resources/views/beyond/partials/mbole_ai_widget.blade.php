{{-- Mbole AI — Website Beyond Assistant (poll-based chat) --}}
<style>
#mbole-ai-root{position:fixed;right:24px;bottom:24px;z-index:99990;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif}
#mbole-ai-root *{box-sizing:border-box}
.mbole-fab{width:64px;height:64px;border-radius:50%;border:0;padding:0;cursor:pointer;background:transparent;position:relative;box-shadow:0 10px 28px rgba(11,61,145,.28);animation:mbole-bob 2.8s ease-in-out infinite}
.mbole-fab img{width:64px;height:64px;border-radius:50%;display:block;object-fit:cover;background:#0b3d91}
.mbole-fab::after{content:"";position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(11,61,145,.22);animation:mbole-glow 2.8s ease-in-out infinite;pointer-events:none}
.mbole-greet{position:absolute;right:76px;bottom:12px;max-width:230px;background:#fff;color:#0f172a;border-radius:14px;padding:12px 14px;box-shadow:0 12px 32px rgba(15,23,42,.18);font-size:13px;line-height:1.4}
.mbole-greet strong{display:block;margin-bottom:4px;font-size:13px}
.mbole-greet button{margin-top:8px;border:0;background:#0b3d91;color:#fff;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer}
.mbole-panel{display:none;width:min(380px,calc(100vw - 24px));height:min(560px,calc(100vh - 48px));background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 22px 60px rgba(15,23,42,.28);flex-direction:column}
.mbole-panel.open{display:flex}
.mbole-head{display:flex;align-items:center;gap:10px;padding:12px 14px;background:linear-gradient(135deg,#0b3d91,#1d4ed8);color:#fff}
.mbole-head img{width:40px;height:40px;border-radius:50%;object-fit:cover;background:#fff}
.mbole-head .meta{flex:1;min-width:0}
.mbole-head .meta strong{display:block;font-size:14px;line-height:1.2}
.mbole-head .meta span{display:block;font-size:11px;opacity:.9}
.mbole-head .online{font-size:11px;opacity:.85}
.mbole-head button{border:0;background:transparent;color:#fff;font-size:18px;line-height:1;cursor:pointer;padding:4px 6px}
.mbole-thread{flex:1;overflow:auto;padding:14px;background:#f8fafc;display:flex;flex-direction:column;gap:10px}
.mbole-msg{max-width:85%;padding:10px 12px;border-radius:14px;font-size:13px;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.mbole-msg.assistant,.mbole-msg.staff{align-self:flex-start;background:#fff;border:1px solid #e2e8f0;color:#0f172a;border-bottom-left-radius:4px}
.mbole-msg.visitor{align-self:flex-end;background:#0b3d91;color:#fff;border-bottom-right-radius:4px}
.mbole-msg.staff{border-left:3px solid #f59e0b}
.mbole-typing{align-self:flex-start;font-size:12px;color:#64748b;display:none}
.mbole-typing.on{display:block}
.mbole-compose{display:flex;gap:8px;padding:10px;border-top:1px solid #e2e8f0;background:#fff}
.mbole-compose input{flex:1;border:1px solid #cbd5e1;border-radius:999px;padding:10px 14px;font-size:13px;outline:none}
.mbole-compose button{border:0;background:#0b3d91;color:#fff;border-radius:999px;padding:0 16px;font-weight:600;cursor:pointer}
.mbole-footer{padding:0 12px 10px;background:#fff;display:flex;justify-content:space-between;align-items:center}
.mbole-footer a{font-size:11px;color:#0b3d91;text-decoration:none}
.mbole-footer button{border:0;background:transparent;color:#64748b;font-size:11px;cursor:pointer}
@keyframes mbole-bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}
@keyframes mbole-glow{0%,100%{opacity:.35;transform:scale(1)}50%{opacity:.8;transform:scale(1.04)}}
@media (max-width:640px){
  #mbole-ai-root{right:max(12px,env(safe-area-inset-right));bottom:max(12px,env(safe-area-inset-bottom))}
  .mbole-fab,.mbole-fab img{width:54px;height:54px}
  .mbole-greet{right:66px;max-width:190px}
  .mbole-panel{position:fixed;inset:auto 0 0 0;width:100vw;height:min(92vh,720px);border-radius:18px 18px 0 0}
}
[x-cloak]{display:none!important}
</style>

<div id="mbole-ai-root" aria-live="polite">
  <div class="mbole-greet" id="mbole-greet" style="display:none" x-cloak>
    <strong id="mbole-greet-title">Hello! How can I help?</strong>
    <button type="button" id="mbole-greet-cta">Let's Chat</button>
  </div>
  <button type="button" class="mbole-fab" id="mbole-fab" aria-label="Open Mbole AI chat">
    <img src="{{ asset('branding/mbole-ai.png') }}" alt="Mbole AI" width="64" height="64">
  </button>
  <div class="mbole-panel" id="mbole-panel" role="dialog" aria-label="Mbole AI chat" x-cloak>
    <div class="mbole-head">
      <img src="{{ asset('branding/mbole-ai.png') }}" alt="">
      <div class="meta">
        <strong id="mbole-name">Mbole AI</strong>
        <span>BeyondTechWorld Assistant</span>
        <span class="online">Online</span>
      </div>
      <button type="button" id="mbole-min" title="Minimize" aria-label="Minimize">–</button>
      <button type="button" id="mbole-close" title="Close" aria-label="Close">×</button>
    </div>
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

<script>
(function () {
  var TOKEN_KEY = 'mbole_ai_token';
  var DISMISS_KEY = 'mbole_ai_greet_dismissed';
  var OPENED_KEY = 'mbole_ai_opened_once';
  var CSRF = document.querySelector('meta[name="csrf-token"]');
  var csrf = CSRF ? CSRF.getAttribute('content') : '';
  var token = localStorage.getItem(TOKEN_KEY) || '';
  var cursor = 0;
  var pollTimer = null;
  var sending = false;
  var cfg = { enabled: true, name: 'Mbole AI', greeting: 'Hello! How can I help?', greeting_delay_ms: 600, continue_whatsapp: true };

  var fab = document.getElementById('mbole-fab');
  var panel = document.getElementById('mbole-panel');
  var greet = document.getElementById('mbole-greet');
  var thread = document.getElementById('mbole-thread');
  var form = document.getElementById('mbole-form');
  var input = document.getElementById('mbole-input');
  var typing = document.getElementById('mbole-typing');
  var waLink = document.getElementById('mbole-wa');

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
      return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; });
    });
  }

  function appendMsg(msg) {
    if (!msg || !msg.id) return;
    if (thread.querySelector('[data-id="' + msg.id + '"]')) return;
    var el = document.createElement('div');
    el.className = 'mbole-msg ' + (msg.role || 'assistant');
    el.setAttribute('data-id', msg.id);
    el.textContent = msg.body || '';
    thread.appendChild(el);
    thread.scrollTop = thread.scrollHeight;
    if (msg.id > cursor) cursor = msg.id;
  }

  function setOpen(open) {
    panel.classList.toggle('open', !!open);
    greet.style.display = 'none';
    if (open) {
      localStorage.setItem(OPENED_KEY, '1');
      ensureSession().then(function () { poll(true); startPoll(); });
      setTimeout(function () { input.focus(); }, 80);
    } else {
      stopPoll();
    }
  }

  function ensureSession() {
    return api('/api/website-chat/session', {
      method: 'POST',
      json: { token: token || null, path: location.pathname }
    }).then(function (res) {
      if (!res.ok || !res.body.success) throw new Error((res.body && res.body.error) || 'session failed');
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
    if (!token) return Promise.resolve();
    var url = '/api/website-chat/messages?token=' + encodeURIComponent(token) + '&after=' + encodeURIComponent(cursor);
    return api(url).then(function (res) {
      if (!res.ok || !res.body.success) return;
      (res.body.messages || []).forEach(appendMsg);
      if (initial && !(res.body.messages || []).length && cfg.greeting && !localStorage.getItem(OPENED_KEY)) {
        /* greeting is idle bubble only; first open can show system line once */
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

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending) return;
    var body = (input.value || '').trim();
    if (!body) return;
    sending = true;
    typing.classList.add('on');
    input.value = '';
    ensureSession().then(function () {
      return api('/api/website-chat/messages', {
        method: 'POST',
        json: { token: token, body: body, path: location.pathname }
      });
    }).then(function (res) {
      typing.classList.remove('on');
      sending = false;
      if (!res.ok || !res.body.success) {
        appendMsg({ id: 'err-' + Date.now(), role: 'assistant', body: (res.body && res.body.error) || 'Sorry, something went wrong.' });
        return;
      }
      (res.body.messages || []).forEach(appendMsg);
    }).catch(function () {
      typing.classList.remove('on');
      sending = false;
      appendMsg({ id: 'err-' + Date.now(), role: 'assistant', body: 'Network error. Please try again.' });
    });
  });

  fab.addEventListener('click', function () { setOpen(true); });
  document.getElementById('mbole-greet-cta').addEventListener('click', function () { setOpen(true); });
  document.getElementById('mbole-close').addEventListener('click', function () { setOpen(false); });
  document.getElementById('mbole-min').addEventListener('click', function () {
    setOpen(false);
    if (token) api('/api/website-chat/minimize', { method: 'POST', json: { token: token } });
  });
  document.getElementById('mbole-dismiss').addEventListener('click', function () {
    localStorage.setItem(DISMISS_KEY, '1');
    greet.style.display = 'none';
  });

  api('/api/website-chat/config').then(function (res) {
    if (!res.ok || !res.body.success) return;
    cfg = Object.assign(cfg, res.body);
    if (!cfg.enabled) {
      document.getElementById('mbole-ai-root').style.display = 'none';
      return;
    }
    if (cfg.name) document.getElementById('mbole-name').textContent = cfg.name;
    if (cfg.greeting) {
      document.getElementById('mbole-greet-title').textContent = cfg.greeting;
    }
    if (!localStorage.getItem(DISMISS_KEY) && !localStorage.getItem(OPENED_KEY)) {
      setTimeout(function () {
        if (!panel.classList.contains('open')) greet.style.display = 'block';
      }, cfg.greeting_delay_ms || 600);
    }
    if (localStorage.getItem(TOKEN_KEY)) {
      token = localStorage.getItem(TOKEN_KEY);
    }
  }).catch(function () {});
})();
</script>

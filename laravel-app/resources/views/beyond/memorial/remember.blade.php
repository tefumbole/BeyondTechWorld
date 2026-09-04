@extends('beyond.memorial.remember-layout')

@section('title', 'Pa Ngwayu Francis · Church program and eulogies')

@section('nav_extra')
    <button type="button" id="openDonate">Donate</button>
    <button type="button" id="openEulogy">Leave a eulogy</button>
@endsection

@section('content')
    @if($flashPay === 'ok')
        <div class="flash">Thank you. Your gift was received.</div>
    @elseif($flashPay === 'failed')
        <div class="flash bad">Payment did not complete. You can try again.</div>
    @elseif($flashPay === 'pending')
        <div class="flash">Payment is pending. It will be confirmed shortly.</div>
    @endif
    @if($flashEulogy === 'ok')
        <div class="flash">Your eulogy was received. Thank you.</div>
    @endif

    <div class="landing-home" id="landingHome">
        <h1>In memory of Pa Ngwayu Francis</h1>
        <div class="rings" id="rings">
            <div class="ring" style="--p:0%"><b id="d">0</b><span id="dLabel">Days</span></div>
            <div class="ring" style="--p:0%"><b id="h">0</b><span id="hLabel">Hours</span></div>
            <div class="ring" style="--p:0%"><b id="m">0</b><span id="mLabel">Mins</span></div>
            <div class="ring" style="--p:0%"><b id="s">0</b><span id="sLabel">Secs</span></div>
        </div>
        <div class="sun-row">
            <div class="sun-item">
                <span>Sunrise</span>
                <strong>1953</strong>
            </div>
            <div class="sun-item">
                <span>Sunset</span>
                <strong>2026</strong>
            </div>
        </div>
        <button type="button" class="eulogy-cta" id="openEulogyBottom">Add a Eulogy</button>
        <div class="qr-card">
            <img src="{{ asset('public/memorial/pangwayu/qr-remember.png') }}" alt="QR code for Pa Ngwayu Francis memorial page">
            <div>
                <strong>Scan to open</strong>
                <p>Anyone who scans this code is taken straight to this memorial page.</p>
                <a href="{{ asset('public/memorial/pangwayu/qr-remember.png') }}" download="pangwayu-remember-qr.png">Download QR</a>
            </div>
        </div>
    </div>

    <section class="eulogies is-hidden" id="eulogies">
        <p class="kicker">Eulogies</p>
        <h2>Eulogies</h2>
        <p class="lead">Enter your phone number and your name will appear from our records, or type it yourself. Sign your eulogy, and you may add a selfie before you submit.</p>
        <span class="eulogies-count">{{ count($eulogies) }} {{ count($eulogies) === 1 ? 'eulogy' : 'eulogies' }} written</span>
        @forelse($eulogies as $eu)
            <article class="eulogy-box">
                <p>{{ $eu['body'] }}</p>
                <div class="eulogy-who">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        @if(!empty($eu['has_selfie']))
                            <img class="selfie" src="{{ $eu['selfie'] }}" alt="{{ $eu['name'] }}">
                        @endif
                        <cite>{{ $eu['name'] }}</cite>
                    </div>
                    @if($eu['has_signature'])
                        <img class="sig" src="{{ $eu['signature'] }}" alt="Signature of {{ $eu['name'] }}">
                    @endif
                </div>
            </article>
        @empty
            <p class="eulogies-empty">Be the first to leave a eulogy for Pa Ngwayu Francis.</p>
        @endforelse
    </section>
@endsection

@section('modals')
<div class="modal" id="donateModal">
    <div class="sheet">
        <button type="button" class="close" id="closeDonate">&times;</button>
        <p class="kicker">A gift in his memory</p>
        <h2>Donate</h2>
        <form id="donateForm">
            <input type="hidden" name="item_id" value="{{ $giftItemId }}">
            <input type="hidden" name="back" value="remember">
            <label>Phone number *</label>
            <div class="phone-row">
                <select name="country_code" id="countryCode">
                    @foreach($countries as $c)
                        <option value="{{ $c['code'] }}" {{ $c['code'] === '+237' ? 'selected' : '' }}>{{ $c['code'] }}</option>
                    @endforeach
                </select>
                <input type="tel" name="phone" id="phone" placeholder="677318405" autocomplete="tel" required>
            </div>
            <label>Name *</label>
            <input type="text" name="name" id="name" required>
            <label>Amount (XAF) *</label>
            <input type="number" name="amount" id="amount" min="100" step="1" required>
            <p class="hint">Give any amount. Totals are not shown on this page.</p>
            <div class="err" id="formErr"></div>
            <div class="actions3">
                <button type="submit" class="btn btn-gold" data-pay="momo">Pay MoMo / OM</button>
                <button type="submit" class="btn btn-gold" data-pay="visa">Pay VISA</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="eulogyModal">
    <div class="sheet">
        <button type="button" class="close" id="closeEulogy">&times;</button>
        <p class="kicker">In loving memory</p>
        <h2>Leave a eulogy</h2>
        <form id="eulogyForm">
            <input type="hidden" name="from" value="remember">
            <label>Phone number *</label>
            <div class="phone-row">
                <select name="country_code" id="euCountry">
                    @foreach($countries as $c)
                        <option value="{{ $c['code'] }}" {{ $c['code'] === '+237' ? 'selected' : '' }}>{{ $c['code'] }}</option>
                    @endforeach
                </select>
                <input type="tel" name="phone" id="euPhone" placeholder="677318405" required>
            </div>
            <label>Name *</label>
            <input type="text" name="name" id="euName" required>
            <label>Eulogy *</label>
            <textarea name="body" id="euBody" rows="7" required placeholder="A few words for Pa Ngwayu Francis…"></textarea>
            <label>Signature *</label>
            <canvas id="sigPad" class="sig-pad"></canvas>
            <div class="sig-actions">
                <button type="button" class="btn btn-ghost btn-sm" id="sigClear">Clear signature</button>
            </div>
            <input type="hidden" name="signature" id="euSig">
            <label>Selfie (optional)</label>
            <div class="selfie-box">
                <div class="selfie-stage" id="selfieStage">
                    <video id="selfieVideo" playsinline autoplay muted></video>
                    <img id="selfieShot" class="selfie-shot" alt="Captured selfie">
                </div>
                <div class="selfie-toolbar">
                    <button type="button" class="btn btn-gold btn-sm" id="selfieCamBtn">Enable camera</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="selfieCaptureBtn" style="display:none;">Take photo</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="selfieRetakeBtn" style="display:none;">Retake</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="selfieUploadBtn">Choose photo</button>
                </div>
                <input type="file" id="euSelfie" accept="image/jpeg,image/png,image/webp" capture="user">
                <div class="selfie-preview-row" id="selfiePreviewRow">
                    <img id="selfiePreview" class="selfie-preview" alt="Selfie preview">
                    <span>Ready to submit · max 256 KB</span>
                </div>
                <p class="hint">Turn on your camera for a live selfie, or choose a photo from your device. We resize every selfie to 256 KB or less.</p>
            </div>
            <div class="err" id="euErr"></div>
            <div class="actions" style="margin-top:12px;">
                <button type="button" class="btn btn-ghost" id="euCancel">Cancel</button>
                <button type="submit" class="btn btn-gold">Submit eulogy</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<audio id="ceremonyAudio" src="{{ asset('public/memorial/pangwayu/audio/it-is-well-instrumental.mp3') }}" loop preload="auto" playsinline autoplay muted></audio>
<div class="music-player" id="musicPlayer" role="group" aria-label="Memorial music">
    <div class="music-controls">
        <button type="button" id="musicPlay" aria-label="Play">
            <svg id="iconPlay" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
            <svg id="iconPause" viewBox="0 0 24 24" aria-hidden="true" style="display:none"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
        </button>
        <button type="button" id="musicFwd" aria-label="Forward 10 seconds" title="Forward 10 seconds">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5v14l8.5-7L4 5zm9 0v14l8.5-7L13 5z"/></svg>
        </button>
    </div>
    <div class="music-meta">
        <span class="music-title">It Is Well With My Soul · piano</span>
        <div class="music-bar" id="musicBar" role="slider" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
            <div class="music-fill" id="musicFill"></div>
        </div>
        <div class="music-time"><span id="musicCur">0:00</span><span id="musicDur">0:00</span></div>
    </div>
</div>
<script>
(function () {
    var audio = document.getElementById('ceremonyAudio');
    var playBtn = document.getElementById('musicPlay');
    var fwdBtn = document.getElementById('musicFwd');
    var iconPlay = document.getElementById('iconPlay');
    var iconPause = document.getElementById('iconPause');
    var fill = document.getElementById('musicFill');
    var bar = document.getElementById('musicBar');
    var curEl = document.getElementById('musicCur');
    var durEl = document.getElementById('musicDur');
    if (audio && playBtn) {
        var userPaused = false;
        var unlockTries = 0;
        audio.volume = 0.48;
        audio.muted = false;
        function fmt(t) {
            if (!isFinite(t) || t < 0) return '0:00';
            var m = Math.floor(t / 60);
            var s = Math.floor(t % 60);
            return m + ':' + (s < 10 ? '0' : '') + s;
        }
        function setMusicUi(on) {
            iconPlay.style.display = on ? 'none' : 'block';
            iconPause.style.display = on ? 'block' : 'none';
            playBtn.setAttribute('aria-label', on ? 'Pause' : 'Play');
        }
        function syncBar() {
            var d = audio.duration || 0;
            var c = audio.currentTime || 0;
            var pct = d ? Math.min(100, (c / d) * 100) : 0;
            fill.style.width = pct + '%';
            if (bar) bar.setAttribute('aria-valuenow', String(Math.round(pct)));
            if (curEl) curEl.textContent = fmt(c);
            if (durEl) durEl.textContent = fmt(d);
        }
        function hear() {
            audio.muted = false;
            audio.volume = 0.48;
            setMusicUi(!audio.paused && !audio.muted);
        }
        function tryPlay() {
            if (userPaused) return;
            var p = audio.play();
            if (p && p.then) {
                p.then(function () {
                    hear();
                    if (audio.muted) {
                        audio.muted = false;
                        audio.play().then(hear).catch(function () {});
                    }
                }).catch(function () {
                    audio.muted = true;
                    audio.play().then(function () {
                        audio.muted = false;
                        audio.volume = 0.48;
                        hear();
                    }).catch(function () { setMusicUi(false); });
                });
            } else if (!audio.paused) {
                hear();
            }
        }
        tryPlay();
        [120, 400, 900, 1800, 3000].forEach(function (ms) { setTimeout(tryPlay, ms); });
        var retry = setInterval(function () {
            unlockTries += 1;
            if (!audio.paused && !audio.muted) { clearInterval(retry); return; }
            if (unlockTries > 20 || userPaused) { clearInterval(retry); return; }
            tryPlay();
        }, 700);
        playBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (audio.paused || audio.muted) {
                userPaused = false;
                audio.muted = false;
                tryPlay();
            } else {
                userPaused = true;
                audio.pause();
                setMusicUi(false);
            }
        });
        if (fwdBtn) {
            fwdBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var d = audio.duration || 0;
                audio.currentTime = Math.min(d, (audio.currentTime || 0) + 10);
                syncBar();
            });
        }
        if (bar) {
            function seekFromEvent(ev) {
                var r = bar.getBoundingClientRect();
                var x = (ev.touches ? ev.touches[0].clientX : ev.clientX) - r.left;
                var pct = Math.max(0, Math.min(1, x / r.width));
                if (audio.duration) audio.currentTime = pct * audio.duration;
                syncBar();
            }
            bar.addEventListener('click', seekFromEvent);
        }
        audio.addEventListener('timeupdate', syncBar);
        audio.addEventListener('loadedmetadata', syncBar);
        audio.addEventListener('play', function () { if (!audio.muted) setMusicUi(true); });
        audio.addEventListener('pause', function () { if (userPaused) setMusicUi(false); });
        ['pointerdown', 'touchstart', 'keydown', 'scroll', 'wheel', 'pointermove'].forEach(function (evt) {
            document.addEventListener(evt, function startOnce() {
                if (!userPaused) tryPlay();
                document.removeEventListener(evt, startOnce);
            }, { once: true, passive: true });
        });
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden && !userPaused) tryPlay();
        });
        window.addEventListener('pageshow', function () {
            if (!userPaused) tryPlay();
        });
    }

    (function skyCeremony() {
        var canvas = document.getElementById('skyFx');
        if (!canvas || !document.body.classList.contains('is-landing')) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var ctx = canvas.getContext('2d');
        var stars = [];
        var petals = [];
        var sparks = [];
        function size() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        function makeStar() {
            return {
                x: Math.random() * canvas.width,
                y: -20 - Math.random() * canvas.height,
                s: 0.6 + Math.random() * 1.8,
                v: 0.35 + Math.random() * 1.1,
                drift: -0.25 + Math.random() * 0.5,
                a: 0.35 + Math.random() * 0.65,
                tw: Math.random() * Math.PI * 2
            };
        }
        function makePetal() {
            return {
                x: Math.random() * canvas.width,
                y: -30 - Math.random() * 80,
                r: 4 + Math.random() * 7,
                v: 0.45 + Math.random() * 0.9,
                drift: -0.4 + Math.random() * 0.8,
                rot: Math.random() * Math.PI * 2,
                spin: -0.02 + Math.random() * 0.04,
                a: 0.28 + Math.random() * 0.45
            };
        }
        function makeSpark() {
            return {
                x: (0.12 + Math.random() * 0.76) * canvas.width,
                y: canvas.height * (0.55 + Math.random() * 0.4),
                s: 0.8 + Math.random() * 1.6,
                v: -0.25 - Math.random() * 0.55,
                a: 0.2 + Math.random() * 0.45,
                life: 80 + Math.random() * 140
            };
        }
        size();
        var mobile = canvas.width < 760;
        var starN = mobile ? 28 : 70;
        var petalN = mobile ? 12 : 28;
        var sparkN = mobile ? 8 : 18;
        for (var i = 0; i < starN; i++) stars.push(makeStar());
        for (var j = 0; j < petalN; j++) petals.push(makePetal());
        for (var k = 0; k < sparkN; k++) sparks.push(makeSpark());
        window.addEventListener('resize', size);
        function tick() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var n;
            for (n = 0; n < stars.length; n++) {
                var st = stars[n];
                st.y += st.v;
                st.x += st.drift;
                st.tw += 0.04;
                if (st.y > canvas.height + 12) {
                    stars[n] = makeStar();
                    stars[n].y = -12;
                    continue;
                }
                var glow = st.a * (0.65 + 0.35 * Math.sin(st.tw));
                ctx.beginPath();
                ctx.fillStyle = 'rgba(255, 236, 180,' + glow + ')';
                ctx.arc(st.x, st.y, st.s, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.strokeStyle = 'rgba(240, 213, 122,' + (glow * 0.45) + ')';
                ctx.lineWidth = 1;
                ctx.moveTo(st.x, st.y - st.s * 5);
                ctx.lineTo(st.x, st.y + st.s * 2);
                ctx.stroke();
            }
            for (n = 0; n < petals.length; n++) {
                var p = petals[n];
                p.y += p.v;
                p.x += p.drift + Math.sin(p.rot) * 0.25;
                p.rot += p.spin;
                if (p.y > canvas.height + 20) {
                    petals[n] = makePetal();
                    continue;
                }
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = 'rgba(255, 248, 232,' + p.a + ')';
                ctx.beginPath();
                ctx.ellipse(0, 0, p.r, p.r * 0.45, 0, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
            }
            for (n = 0; n < sparks.length; n++) {
                var sp = sparks[n];
                sp.y += sp.v;
                sp.life -= 1;
                if (sp.life < 0 || sp.y < canvas.height * 0.2) {
                    sparks[n] = makeSpark();
                    continue;
                }
                ctx.beginPath();
                ctx.fillStyle = 'rgba(255, 210, 120,' + sp.a + ')';
                ctx.arc(sp.x, sp.y, sp.s, 0, Math.PI * 2);
                ctx.fill();
            }
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    })();

    var LOOKUP = @json($lookupUrl);
    var POST = @json($pledgeUrl);
    var EULOGY = @json($eulogyUrl);
    var GIFT = @json($giftItemId);
    var END = new Date(@json($funeralAt)).getTime();
    var payMethod = 'momo';
    var selfieBlob = null;
    var eulogySection = document.getElementById('eulogies');
    function showEulogies() {
        if (!eulogySection) return;
        eulogySection.classList.remove('is-hidden');
        eulogySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (window.location.hash === '#eulogies' || window.location.search.indexOf('eulogy=ok') !== -1) {
        eulogySection.classList.remove('is-hidden');
    }
    Array.prototype.forEach.call(document.querySelectorAll('a[href*="#eulogies"]'), function (link) {
        link.addEventListener('click', function (e) {
            if (eulogySection) {
                e.preventDefault();
                history.replaceState(null, '', '#eulogies');
                showEulogies();
            }
        });
    });

    function tick() {
        var left = Math.max(0, END - Date.now());
        var d = Math.floor(left / 86400000);
        var h = Math.floor((left % 86400000) / 3600000);
        var m = Math.floor((left % 3600000) / 60000);
        var s = Math.floor((left % 60000) / 1000);
        document.getElementById('d').textContent = d;
        document.getElementById('h').textContent = h;
        document.getElementById('m').textContent = m;
        document.getElementById('s').textContent = s;
        document.getElementById('dLabel').textContent = d === 1 ? 'Day' : 'Days';
        document.getElementById('hLabel').textContent = h === 1 ? 'Hour' : 'Hours';
        document.getElementById('mLabel').textContent = m === 1 ? 'Min' : 'Mins';
        document.getElementById('sLabel').textContent = s === 1 ? 'Sec' : 'Secs';
        var rings = document.querySelectorAll('.ring');
        var parts = [d / 30, h / 24, m / 60, s / 60];
        for (var i = 0; i < rings.length; i++) {
            rings[i].style.setProperty('--p', Math.min(100, Math.round(parts[i] * 100)) + '%');
        }
    }
    tick();
    setInterval(tick, 1000);

    function lookupInto(phoneEl, codeEl, nameEl) {
        var phone = phoneEl.value;
        var code = codeEl.value;
        if (String(phone).replace(/\D/g, '').length < 8) return;
        fetch(LOOKUP + '?country_code=' + encodeURIComponent(code) + '&phone=' + encodeURIComponent(phone), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data) return;
            var n = data.name || data.system_name || data.original_name;
            if (n) nameEl.value = n;
        }).catch(function () {});
    }

    var donateModal = document.getElementById('donateModal');
    function openDonate() {
        if (!GIFT) {
            alert('Donations are not open yet.');
            return;
        }
        donateModal.classList.add('on');
        document.getElementById('phone').focus();
    }
    document.getElementById('openDonate').onclick = openDonate;
    if (window.location.search.indexOf('donate=1') !== -1) {
        openDonate();
    }
    document.getElementById('closeDonate').onclick = function () { donateModal.classList.remove('on'); };
    donateModal.addEventListener('click', function (e) { if (e.target === donateModal) donateModal.classList.remove('on'); });

    var dTimer = null;
    document.getElementById('phone').addEventListener('input', function () {
        clearTimeout(dTimer);
        dTimer = setTimeout(function () {
            lookupInto(document.getElementById('phone'), document.getElementById('countryCode'), document.getElementById('name'));
        }, 450);
    });
    document.getElementById('phone').addEventListener('blur', function () {
        lookupInto(document.getElementById('phone'), document.getElementById('countryCode'), document.getElementById('name'));
    });

    document.getElementById('donateForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('formErr');
        err.textContent = '';
        var body = new FormData(e.target);
        body.append('action', 'pay');
        body.append('pay_method', payMethod);
        fetch(POST, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: body
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (res.j && res.j.redirect) {
                  window.location = res.j.redirect;
                  return;
              }
              if (!res.ok || !res.j.ok) {
                  err.textContent = (res.j && res.j.message) || 'Could not start payment.';
                  return;
              }
              window.location.reload();
          }).catch(function () { err.textContent = 'Network error. Try again.'; });
    });
    Array.prototype.forEach.call(document.querySelectorAll('#donateForm [data-pay]'), function (btn) {
        btn.addEventListener('click', function () { payMethod = btn.getAttribute('data-pay') || 'momo'; });
    });

    var euModal = document.getElementById('eulogyModal');
    var canvas = document.getElementById('sigPad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var cameraStream = null;
    var videoEl = document.getElementById('selfieVideo');
    var shotEl = document.getElementById('selfieShot');
    var stageEl = document.getElementById('selfieStage');
    var camBtn = document.getElementById('selfieCamBtn');
    var captureBtn = document.getElementById('selfieCaptureBtn');
    var retakeBtn = document.getElementById('selfieRetakeBtn');
    var uploadBtn = document.getElementById('selfieUploadBtn');
    var previewRow = document.getElementById('selfiePreviewRow');
    var preview = document.getElementById('selfiePreview');

    function sizeCanvas() {
        var r = canvas.getBoundingClientRect();
        canvas.width = Math.floor(r.width);
        canvas.height = Math.floor(r.height);
        ctx.strokeStyle = '#1a1408';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
    }
    function pos(ev) {
        var r = canvas.getBoundingClientRect();
        var t = ev.touches ? ev.touches[0] : ev;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    }
    canvas.addEventListener('mousedown', function (ev) { drawing = true; var p = pos(ev); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('mousemove', function (ev) { if (!drawing) return; var p = pos(ev); ctx.lineTo(p.x, p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup', function () { drawing = false; });
    canvas.addEventListener('mouseleave', function () { drawing = false; });
    canvas.addEventListener('touchstart', function (ev) { ev.preventDefault(); drawing = true; var p = pos(ev); ctx.beginPath(); ctx.moveTo(p.x, p.y); }, { passive: false });
    canvas.addEventListener('touchmove', function (ev) { ev.preventDefault(); if (!drawing) return; var p = pos(ev); ctx.lineTo(p.x, p.y); ctx.stroke(); }, { passive: false });
    canvas.addEventListener('touchend', function () { drawing = false; });
    document.getElementById('sigClear').onclick = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); };

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (t) { t.stop(); });
            cameraStream = null;
        }
        if (videoEl) videoEl.srcObject = null;
    }
    function resetSelfieUi(keepBlob) {
        if (!keepBlob) {
            selfieBlob = null;
            if (preview) preview.removeAttribute('src');
            if (previewRow) previewRow.classList.remove('on');
            if (shotEl) {
                shotEl.classList.remove('on');
                shotEl.removeAttribute('src');
            }
        }
        if (stageEl) stageEl.classList.remove('on');
        if (videoEl) videoEl.classList.remove('is-hidden');
        if (captureBtn) captureBtn.style.display = 'none';
        if (retakeBtn) retakeBtn.style.display = 'none';
        if (camBtn) {
            camBtn.style.display = '';
            camBtn.textContent = 'Enable camera';
        }
        stopCamera();
    }
    function showSelfiePreview(blob) {
        selfieBlob = blob;
        if (preview) {
            preview.src = URL.createObjectURL(blob);
        }
        if (previewRow) previewRow.classList.add('on');
    }
    function setSelfieFromBlob(blob, err) {
        if (err) {
            document.getElementById('euErr').textContent = err;
            return;
        }
        document.getElementById('euErr').textContent = '';
        showSelfiePreview(blob);
    }
    async function startCamera() {
        document.getElementById('euErr').textContent = '';
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            document.getElementById('euErr').textContent = 'Camera is not supported in this browser. Choose a photo instead.';
            return;
        }
        try {
            stopCamera();
            cameraStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } }
            });
            videoEl.srcObject = cameraStream;
            await videoEl.play();
            stageEl.classList.add('on');
            videoEl.classList.remove('is-hidden');
            shotEl.classList.remove('on');
            captureBtn.style.display = '';
            retakeBtn.style.display = 'none';
            camBtn.textContent = 'Camera on';
        } catch (err) {
            document.getElementById('euErr').textContent = 'Could not open the camera. Allow camera access, or choose a photo.';
        }
    }
    function captureSelfie() {
        if (!videoEl || !cameraStream) return;
        var w = videoEl.videoWidth || 640;
        var h = videoEl.videoHeight || 480;
        var c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(videoEl, 0, 0, w, h);
        c.toBlob(function (blob) {
            if (!blob) {
                document.getElementById('euErr').textContent = 'Could not capture photo.';
                return;
            }
            compressSelfie(blob, function (out, err) {
                setSelfieFromBlob(out, err);
                if (!err && out) {
                    shotEl.src = URL.createObjectURL(out);
                    shotEl.classList.add('on');
                    videoEl.classList.add('is-hidden');
                    captureBtn.style.display = 'none';
                    retakeBtn.style.display = '';
                    stopCamera();
                    camBtn.textContent = 'Enable camera';
                }
            });
        }, 'image/jpeg', 0.92);
    }
    if (camBtn) camBtn.addEventListener('click', startCamera);
    if (captureBtn) captureBtn.addEventListener('click', captureSelfie);
    if (retakeBtn) retakeBtn.addEventListener('click', function () {
        selfieBlob = null;
        if (previewRow) previewRow.classList.remove('on');
        shotEl.classList.remove('on');
        startCamera();
    });
    if (uploadBtn) uploadBtn.addEventListener('click', function () {
        document.getElementById('euSelfie').click();
    });

    function closeEulogyModal() {
        euModal.classList.remove('on');
        stopCamera();
    }
    function openEulogyModal() {
        euModal.classList.add('on');
        sizeCanvas();
        document.getElementById('euPhone').focus();
    }
    document.getElementById('openEulogy').onclick = openEulogyModal;
    var openBottom = document.getElementById('openEulogyBottom');
    if (openBottom) openBottom.onclick = openEulogyModal;
    document.getElementById('closeEulogy').onclick = closeEulogyModal;
    document.getElementById('euCancel').onclick = closeEulogyModal;
    euModal.addEventListener('click', function (e) { if (e.target === euModal) closeEulogyModal(); });

    var euTimer = null;
    document.getElementById('euPhone').addEventListener('input', function () {
        clearTimeout(euTimer);
        euTimer = setTimeout(function () {
            lookupInto(document.getElementById('euPhone'), document.getElementById('euCountry'), document.getElementById('euName'));
        }, 450);
    });
    document.getElementById('euPhone').addEventListener('blur', function () {
        lookupInto(document.getElementById('euPhone'), document.getElementById('euCountry'), document.getElementById('euName'));
    });

    function compressSelfie(file, done) {
        if (!file) { done(null); return; }
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function () {
            var max = 960;
            var w = img.width, h = img.height;
            if (w > max || h > max) {
                var scale = max / Math.max(w, h);
                w = Math.round(w * scale);
                h = Math.round(h * scale);
            }
            var c = document.createElement('canvas');
            c.width = w; c.height = h;
            c.getContext('2d').drawImage(img, 0, 0, w, h);
            URL.revokeObjectURL(url);
            var q = 0.82;
            function tryQ() {
                c.toBlob(function (blob) {
                    if (blob && blob.size > 256 * 1024 && q > 0.38) {
                        q -= 0.1;
                        tryQ();
                        return;
                    }
                    if (!blob || blob.size > 256 * 1024) {
                        done(null, 'Selfie must be 256 KB or smaller.');
                        return;
                    }
                    done(blob);
                }, 'image/jpeg', q);
            }
            tryQ();
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            done(null, 'Could not read that photo.');
        };
        img.src = url;
    }

    document.getElementById('euSelfie').addEventListener('change', function (e) {
        var file = e.target.files && e.target.files[0];
        document.getElementById('euErr').textContent = '';
        if (!file) return;
        stopCamera();
        if (stageEl) stageEl.classList.remove('on');
        if (captureBtn) captureBtn.style.display = 'none';
        if (retakeBtn) retakeBtn.style.display = 'none';
        if (camBtn) camBtn.textContent = 'Enable camera';
        compressSelfie(file, function (blob, err) {
            if (err) {
                document.getElementById('euErr').textContent = err;
                e.target.value = '';
                return;
            }
            showSelfiePreview(blob);
        });
    });

    document.getElementById('eulogyForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('euErr');
        err.textContent = '';
        var blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        var signed = canvas.toDataURL() !== blank.toDataURL();
        if (!signed) {
            err.textContent = 'Please sign the eulogy before submitting.';
            return;
        }
        document.getElementById('euSig').value = canvas.toDataURL('image/png');
        var body = new FormData(e.target);
        body.delete('selfie');
        if (selfieBlob) {
            body.append('selfie', selfieBlob, 'selfie.jpg');
        }
        fetch(EULOGY, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: body
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (!res.ok || !res.j.ok) {
                  err.textContent = (res.j && res.j.message) || 'Could not save.';
                  return;
              }
              stopCamera();
              window.location = window.location.pathname + '?eulogy=ok#eulogies';
          }).catch(function () { err.textContent = 'Network error. Try again.'; });
    });
})();
</script>
@endsection

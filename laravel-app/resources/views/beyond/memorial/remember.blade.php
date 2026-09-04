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
                <strong>1952</strong>
            </div>
            <div class="sun-item">
                <span>Sunset</span>
                <strong>2025</strong>
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
            <button type="button" class="btn btn-ghost" id="sigClear" style="margin-top:8px;">Clear signature</button>
            <input type="hidden" name="signature" id="euSig">
            <label>Selfie (optional)</label>
            <input type="file" id="euSelfie" accept="image/jpeg,image/png,image/webp" capture="user">
            <p class="hint">Optional. We resize every selfie to 256 KB or less.</p>
            <img id="selfiePreview" class="selfie-preview" alt="Selfie preview">
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
<script>
(function () {
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
    document.getElementById('openEulogy').onclick = function () { euModal.classList.add('on'); sizeCanvas(); document.getElementById('euPhone').focus(); };
    document.getElementById('closeEulogy').onclick = function () { euModal.classList.remove('on'); };
    document.getElementById('euCancel').onclick = function () { euModal.classList.remove('on'); };
    euModal.addEventListener('click', function (e) { if (e.target === euModal) euModal.classList.remove('on'); });

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
        selfieBlob = null;
        var preview = document.getElementById('selfiePreview');
        preview.style.display = 'none';
        document.getElementById('euErr').textContent = '';
        if (!file) return;
        compressSelfie(file, function (blob, err) {
            if (err) {
                document.getElementById('euErr').textContent = err;
                e.target.value = '';
                return;
            }
            selfieBlob = blob;
            preview.src = URL.createObjectURL(blob);
            preview.style.display = 'block';
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
              window.location = window.location.pathname + '?eulogy=ok#eulogies';
          }).catch(function () { err.textContent = 'Network error. Try again.'; });
    });
})();
</script>
@endsection

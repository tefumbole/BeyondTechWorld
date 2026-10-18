@php
    $rows = isset($testimonials) ? $testimonials : collect();
@endphp
<style>
    .tm-kicker { margin: 0 0 8px; color: var(--gold-deep); font-size: 12px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; }
    .tm-actions { margin: 8px 0 22px; }
    .tm-btn { border: 0; background: var(--forest); color: var(--cream); border-radius: 8px; padding: 10px 16px; font-weight: 700; cursor: pointer; }
    .tm-btn.ghost { background: transparent; color: var(--forest); border: 1px solid var(--forest); }
    .tm-form { background: #fff; border: 1px solid #e4dcc4; border-radius: 12px; padding: 18px; margin-bottom: 28px; }
    .tm-form label { display: block; font-weight: 700; margin: 12px 0 4px; }
    .tm-form input, .tm-form textarea, .tm-form select { width: 100%; border: 1px solid #d7deea; border-radius: 8px; padding: 10px 12px; font: inherit; }
    .tm-phone { display: flex; gap: 8px; align-items: center; }
    .tm-shot { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .tm-shot button { margin: 0; }
    .tm-preview { width: 96px; height: 96px; object-fit: cover; border-radius: 12px; border: 1px solid #e4dcc4; }
    .tm-pad { width: 100%; max-width: 520px; height: 140px; border: 2px dashed var(--forest); border-radius: 12px; background: #fff; touch-action: none; }
    .tm-err { color: #9b2c2c; font-weight: 700; }
    .tm-ok { background: #e7f6ec; border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; }
    .tm-toc { margin: 10px 0 28px; }
    .tm-toc p { margin: 0 0 10px; color: var(--gold-deep); font-size: 12px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; }
    .tm-toc ol { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr 1fr; column-gap: 28px; counter-reset: toc; }
    .tm-toc li { counter-increment: toc; border-top: 1px solid #e4dcc4; }
    .tm-toc a { display: flex; gap: 12px; align-items: baseline; padding: 10px 4px; color: var(--ink); text-decoration: none; font-family: Georgia, Palatino, serif; font-size: 1.05rem; }
    .tm-toc a:before { content: counter(toc, decimal-leading-zero); color: var(--gold-deep); font-family: inherit; font-size: 12px; font-weight: 700; letter-spacing: .08em; }
    .tm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .tm-card { background: #fff; border: 1px solid #e4dcc4; border-radius: 12px; padding: 22px; scroll-margin-top: 90px; }
    .tm-top { display: flex; align-items: center; gap: 12px; padding-bottom: 14px; border-bottom: 1px solid #eee6d2; }
    .tm-avatar { width: 52px; height: 52px; border-radius: 50%; overflow: hidden; background: #eee9db; border: 1px solid var(--gold-deep); display: grid; place-items: center; font-family: Georgia, Palatino, serif; color: var(--forest); }
    .tm-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .tm-top h2 { margin: 0; font-size: 1.15rem; }
    .tm-top time { display: block; color: #6d7a70; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .tm-num { margin-left: auto; color: #c6b48a; font-family: Georgia, Palatino, serif; font-size: 1.6rem; }
    .tm-card p { margin: 14px 0 0; font-size: 1.05rem; }
    .tm-sign { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; margin-top: 16px; padding-top: 10px; border-top: 1px solid #eee6d2; }
    .tm-sign cite { font-family: Georgia, Palatino, serif; font-style: italic; }
    .tm-sign img { height: 52px; max-width: 180px; object-fit: contain; }
    .tm-hint { color: #5c6b62; font-size: .95rem; }
    @media (max-width: 800px) {
        .tm-toc ol, .tm-grid { grid-template-columns: 1fr; }
        .tm-form, .tm-card { padding: 14px; }
        .tm-phone, .tm-sign { flex-wrap: wrap; }
        .tm-actions .tm-btn { display: block; width: 100%; text-align: center; }
    }
</style>

<p class="tm-kicker">Words from the ex-students</p>
<h1>Testimonials</h1>
<p>Write a testimony about GBHS Kumbo. Sign it, add a selfie if you wish, and you will receive a copy on WhatsApp.</p>
@if(request('testimony') === 'ok')
    <div class="tm-ok">Your testimony was received. A copy is on its way to your WhatsApp.</div>
@endif
<div class="tm-actions">
    <button type="button" class="tm-btn" id="tmOpen">Leave a testimony</button>
</div>
<form class="tm-form" id="tmForm" style="display:none;" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <label>Phone number *</label>
    <div class="tm-phone">
        @include('gbhs.public.partials.country_code')
        <input type="tel" name="phone" id="tmPhone" placeholder="675321739" required>
    </div>
    <label>Name *</label>
    <input type="text" name="name" id="tmName" required>
    <p class="tm-hint" id="tmNameHint">Type your number. We use the Mobile Money name when we find it. You can edit it.</p>
    <label>Testimony about GBHS Kumbo *</label>
    <textarea name="body" id="tmBody" rows="7" required maxlength="20000" placeholder="What GBHS Kumbo means to you…"></textarea>
    <label>Signature *</label>
    <canvas id="tmPad" class="tm-pad" width="500" height="140"></canvas>
    <div style="margin-top:8px;"><button type="button" class="tm-btn ghost" id="tmClear">Clear signature</button></div>
    <input type="hidden" name="signature" id="tmSig">
    <label>Selfie</label>
    <div class="tm-shot">
        <button type="button" class="tm-btn" id="tmCam">Take a selfie</button>
        <button type="button" class="tm-btn ghost" id="tmAttach">Attach a photo</button>
        <img class="tm-preview" id="tmPreview" alt="" hidden>
    </div>
    <input type="file" name="selfie" id="tmSelfie" accept="image/*" hidden>
    <p class="tm-hint">Take a selfie to open the camera, or attach a photo you already have. We keep it at 256 KB or less.</p>
    <p class="tm-err" id="tmErr"></p>
    <div style="margin-top:12px;">
        <button type="submit" class="tm-btn">Submit testimony</button>
    </div>
</form>

@if($rows->count())
    <nav class="tm-toc" aria-label="Testimonials">
        <p>Contents</p>
        <ol>
            @foreach($rows as $index => $row)
                <li><a href="#tribute-{{ $index + 1 }}">{{ $row->name }}</a></li>
            @endforeach
        </ol>
    </nav>
    <div class="tm-grid">
        @foreach($rows as $index => $row)
            <article class="tm-card" id="tribute-{{ $index + 1 }}">
                <div class="tm-top">
                    <div class="tm-avatar">
                        @if($row->selfie_path)
                            <img src="{{ url('public/'.$row->selfie_path) }}" alt="">
                        @else
                            {{ strtoupper(substr($row->name, 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <h2>{{ $row->name }}</h2>
                        <time>{{ optional($row->created_at)->timezone('Africa/Douala')->format('d M Y') }}</time>
                    </div>
                    <span class="tm-num">{{ sprintf('%02d', $index + 1) }}</span>
                </div>
                @foreach($row->paragraphs() as $para)
                    <p>{{ $para }}</p>
                @endforeach
                @if($row->signature_path)
                    <div class="tm-sign">
                        <cite>{{ $row->name }}</cite>
                        <img src="{{ url('public/'.$row->signature_path) }}" alt="Signature of {{ $row->name }}">
                    </div>
                @endif
            </article>
        @endforeach
    </div>
@else
    <p>Testimonials will appear here when ex-students add them.</p>
@endif

<script>
(function () {
    var open = document.getElementById('tmOpen');
    var form = document.getElementById('tmForm');
    var pad = document.getElementById('tmPad');
    var drawing = false;
    var dirty = false;
    var ctx = pad.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#12261a';
    function pos(e) {
        var r = pad.getBoundingClientRect();
        var src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - r.left) * (pad.width / r.width), y: (src.clientY - r.top) * (pad.height / r.height) };
    }
    function start(e) { drawing = true; dirty = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
    function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
    function end() { drawing = false; }
    pad.addEventListener('mousedown', start);
    pad.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    pad.addEventListener('touchstart', start, {passive: false});
    pad.addEventListener('touchmove', move, {passive: false});
    pad.addEventListener('touchend', end);
    document.getElementById('tmClear').addEventListener('click', function () {
        ctx.clearRect(0, 0, pad.width, pad.height);
        dirty = false;
    });
    open.addEventListener('click', function () {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') form.scrollIntoView({behavior: 'smooth', block: 'start'});
    });
    var phone = document.getElementById('tmPhone');
    var hint = document.getElementById('tmNameHint');
    gbhsLookupName(phone, form.querySelector('[data-cc-value]'), document.getElementById('tmName'), hint);
    var selfie = document.getElementById('tmSelfie');
    var preview = document.getElementById('tmPreview');
    document.getElementById('tmCam').addEventListener('click', function () { gbhsPickPhoto(selfie, true, preview); });
    document.getElementById('tmAttach').addEventListener('click', function () { gbhsPickPhoto(selfie, false, preview); });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var err = document.getElementById('tmErr');
        err.textContent = '';
        if (!dirty) { err.textContent = 'Please sign the testimony before submitting.'; return; }
        document.getElementById('tmSig').value = pad.toDataURL('image/png');
        var body = new FormData(form);
        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        fetch(@json(route('gbhs.testimonials.store')), {method: 'POST', body: body, headers: {Accept: 'application/json'}})
            .then(function (r) { return r.json().then(function (j) { return {ok: r.ok, j: j}; }); })
            .then(function (res) {
                btn.disabled = false;
                if (!res.ok || !res.j.ok) {
                    err.textContent = (res.j && (res.j.message || (res.j.errors && Object.values(res.j.errors)[0][0]))) || 'Could not save the testimony.';
                    return;
                }
                window.location = @json(route('gbhs.page', 'leaders')) + '?testimony=ok';
            })
            .catch(function () { btn.disabled = false; err.textContent = 'Could not save the testimony. Try again.'; });
    });
})();
</script>

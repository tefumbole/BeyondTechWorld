<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setting->seo_title ?? 'GBHS Kumbo Ex-Students' }}</title>
    <meta name="description" content="{{ $setting->seo_description ?? 'GBHS Kumbo Ex-Students Association' }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&family=Source+Sans+3:wght@400;600;700&display=swap">
    <style>
        :root {
            --forest: #0c3a22;
            --forest-deep: #071f13;
            --leaf: #1d6a3a;
            --gold: #e8c15a;
            --gold-deep: #c8962e;
            --royal: #1b4e9b;
            --cream: #f7f3e8;
            --ink: #12261a;
            --logo-green: #1b432a;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Source Sans 3", sans-serif; font-size: 1.2rem; line-height: 1.6; color: var(--ink); background: var(--cream); }
        a { color: var(--leaf); }
        header { background: var(--forest-deep); color: var(--cream); }
        .bar { display: flex; align-items: center; gap: 28px; padding: 10px 3vw; width: 100%; }
        nav { display: flex; flex-wrap: wrap; gap: 8px 22px; flex: 1; }
        nav a { color: var(--cream); text-decoration: none; font-size: 1.15rem; font-weight: 600; }
        nav a:hover { color: var(--gold); }
        .login {
            margin-left: auto;
            color: var(--gold);
            border: 1px solid var(--gold);
            background: var(--forest-deep);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.05rem;
            border-radius: 6px;
            padding: 10px 18px;
            white-space: nowrap;
        }
        .login:hover { background: var(--gold); color: var(--forest-deep); }
        main { padding: 0 0 72px; width: 100%; }
        .page { width: 100%; max-width: none; margin: 0; padding: 32px 3vw 0; }
        h1, h2, h3 { font-family: "Source Serif 4", serif; color: var(--forest); line-height: 1.2; }
        h1 { font-size: 2.6rem; margin: 0 0 12px; }
        h2 { font-size: 1.8rem; }
        p { font-size: 1.2rem; }
        .card { background: #fffdf8; border: 1px solid #e4d3a2; border-radius: 12px; padding: 18px 20px; margin: 14px 0; font-size: 1.15rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
        label { display: block; font-weight: 700; margin-top: 12px; font-size: 1.05rem; }
        input, select, textarea { width: 100%; padding: 12px 14px; border: 1px solid #d7c59a; border-radius: 8px; font-size: 1.1rem; font-family: inherit; }
        button, .btn { background: var(--forest); color: var(--gold); border: 0; border-radius: 8px; padding: 12px 18px; text-decoration: none; display: inline-block; margin: 14px 10px 0 0; font-size: 1.1rem; font-weight: 700; cursor: pointer; }
        .btn.gold { background: var(--gold); color: var(--forest-deep); }
        .muted { color: #3d5346; font-size: 1.15rem; }
        .alert { padding: 12px 14px; border-radius: 8px; background: #fff4d2; margin-bottom: 16px; }
        img.fit { max-width: 100%; height: auto; border-radius: 12px; }
        footer { color: #f4e7c3; font-size: 1.05rem; background: #071833; }
        footer a { color: var(--gold); text-decoration: none; }
        .foot-mosaic { width: 100%; height: auto; display: block; }
        .foot-band {
            position: relative;
            min-height: 64px;
            background: #071833 url("{{ url('public/gbhs/gbhs-footer-band.jpg') }}") center / 100% 100% no-repeat;
        }
        .foot-copy {
            position: relative;
            z-index: 1;
            min-height: 64px;
            padding: 10px 4vw;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 6px 24px;
        }
        .foot-copy p { margin: 0; font-size: .98rem; color: #f7f1df; }
        .contact-link { font-weight: 700; font-size: 1.05rem; }
        .cc { position: relative; flex: 0 0 auto; }
        .cc-btn { margin: 0; width: auto; min-width: 118px; display: flex; align-items: center; gap: 6px; background: #fff; color: var(--ink); border: 1px solid #d7c59a; }
        .cc-menu { position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; width: 280px; background: #fff; border: 1px solid #e4d3a2; border-radius: 10px; box-shadow: 0 12px 30px rgba(7, 31, 19, .16); }
        .cc-menu input { border: 0; border-bottom: 1px solid #efe6d2; border-radius: 10px 10px 0 0; }
        .cc-menu ul { list-style: none; margin: 0; padding: 4px 0; max-height: 260px; overflow: auto; }
        .cc-menu li { display: flex; gap: 8px; align-items: center; padding: 8px 12px; cursor: pointer; }
        .cc-menu li:hover, .cc-menu li.is-on { background: #f7f3e8; }
        .cc-menu .name { flex: 1; }
        .cc-menu .dial { color: #6d7a70; }
        .phone-row { display: flex; gap: 8px; align-items: center; }
        .phone-row input[type="tel"] { flex: 1; }
        body.home {
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #063018;
        }
        body.home header, body.home footer { flex: 0 0 auto; }
        body.home main { display: none; }
        body.home .hero-land {
            flex: 1 1 auto;
            min-height: 0;
            width: 100%;
            overflow: hidden;
            background: #063018;
        }
        body.home .hero-land img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center bottom;
            display: block;
        }
        body.testimonials .page { width: 100%; }
        @media (max-width: 800px) {
            .bar { flex-wrap: wrap; }
            nav { order: 3; width: 100%; }
            h1 { font-size: 2.1rem; }
            .cc-menu { width: min(280px, 80vw); }
        }
    </style>
    <script>
        window.gbhsLookupName = function (phoneEl, codeEl, nameEl, hintEl) {
            var timer = null;
            var filled = '';
            function run() {
                var digits = (phoneEl.value || '').replace(/\D/g, '');
                if (digits.length < 8) return;
                if (hintEl) hintEl.textContent = 'Checking this number…';
                var url = @json(route('gbhs.donate.lookup'))
                    + '?phone=' + encodeURIComponent(digits)
                    + '&country_code=' + encodeURIComponent((codeEl && codeEl.value) || '+237');
                fetch(url, {headers: {Accept: 'application/json'}})
                    .then(function (r) { return r.json(); })
                    .then(function (body) {
                        if (body && body.name) {
                            if (!nameEl.value || nameEl.value === filled) {
                                nameEl.value = body.name;
                                filled = body.name;
                            }
                            if (hintEl) hintEl.textContent = 'Name found. You can edit it.';
                        } else if (hintEl) {
                            hintEl.textContent = 'No Mobile Money name was found. Type the name. You can edit it.';
                        }
                    })
                    .catch(function () {
                        if (hintEl) hintEl.textContent = 'The name could not be checked. Type it, then edit it if you need to.';
                    });
            }
            phoneEl.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(run, 400);
            });
            if (codeEl) codeEl.addEventListener('change', run);
        };
        window.gbhsPickPhoto = function (input, camera, preview) {
            function showFile() {
                input.onchange = function () {
                    if (!preview || !input.files || !input.files[0]) return;
                    preview.src = URL.createObjectURL(input.files[0]);
                    preview.hidden = false;
                };
            }
            if (!camera || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                if (camera) input.setAttribute('capture', 'user');
                else input.removeAttribute('capture');
                showFile();
                input.click();
                return;
            }
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(7,31,19,.82);z-index:80;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:16px;';
            var video = document.createElement('video');
            video.setAttribute('playsinline', '');
            video.autoplay = true;
            video.style.cssText = 'width:min(100%,520px);max-height:70vh;border-radius:16px;background:#000;';
            var shoot = document.createElement('button');
            shoot.type = 'button';
            shoot.textContent = 'Use this photo';
            shoot.className = 'btn gold';
            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.textContent = 'Cancel';
            cancel.className = 'btn';
            overlay.appendChild(video);
            var row = document.createElement('div');
            row.appendChild(shoot);
            row.appendChild(cancel);
            overlay.appendChild(row);
            document.body.appendChild(overlay);
            var stream = null;
            function stop() {
                if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
                if (overlay.parentNode) overlay.remove();
            }
            navigator.mediaDevices.getUserMedia({video: {facingMode: 'user'}, audio: false}).then(function (opened) {
                stream = opened;
                video.srcObject = opened;
            }).catch(function () {
                stop();
                input.setAttribute('capture', 'user');
                showFile();
                input.click();
            });
            cancel.addEventListener('click', stop);
            shoot.addEventListener('click', function () {
                var canvas = document.createElement('canvas');
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                canvas.getContext('2d').drawImage(video, 0, 0);
                canvas.toBlob(function (blob) {
                    if (blob && window.DataTransfer) {
                        var file = new File([blob], 'selfie.jpg', {type: 'image/jpeg'});
                        var transfer = new DataTransfer();
                        transfer.items.add(file);
                        input.files = transfer.files;
                        if (preview) {
                            preview.src = URL.createObjectURL(blob);
                            preview.hidden = false;
                        }
                    }
                    stop();
                }, 'image/jpeg', 0.86);
            });
        };
    </script>
</head>
<body class="@yield('body-class')">
@php
    $contactPhone = trim((string) ($setting->phone ?? ''));
    $contactWho = trim((string) ($setting->president_name ?? ''));
    if ($contactWho === '') {
        $contactWho = 'the President';
    }
    if ($contactPhone === '') {
        try {
            $leader = \App\Gbhs\GbhsLeader::where('published', 1)
                ->where('role', 'like', '%resident%')
                ->where('phone', '!=', '')
                ->orderBy('sort')
                ->first();
            if ($leader) {
                $contactPhone = trim((string) $leader->phone);
                if (trim((string) $leader->name) !== '') {
                    $contactWho = $leader->name;
                }
            }
        } catch (\Throwable $e) {
            $leader = null;
        }
    }
    $contactDigits = preg_replace('/\D/', '', $contactPhone);
@endphp
<header>
    <div class="bar">
        <nav>
            <a href="{{ route('gbhs.home') }}">Home</a>
            <a href="{{ route('gbhs.page', 'about') }}">About Us</a>
            <a href="{{ route('gbhs.membership') }}">Membership</a>
            <a href="{{ route('gbhs.page', 'leaders') }}">Testimonials</a>
            <a href="{{ route('gbhs.donate') }}">Donate</a>
            <a href="{{ route('gbhs.page', 'contact') }}">Contact us</a>
        </nav>
        <a class="login" href="{{ url('/login') }}">Login</a>
    </div>
</header>
@yield('hero')
<main>
    <div class="page">
        @if(session('message'))<div class="alert">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert">{{ session('not_permitted') }}</div>@endif
        @yield('content')
    </div>
</main>
<footer>
    <img class="foot-mosaic" src="{{ url('public/gbhs/gbhs-footer-mosaic.png') }}" alt="">
    <div class="foot-band">
        <div class="foot-copy">
            <div>
                @if($contactDigits !== '')
                    <a class="contact-link" href="tel:+{{ $contactDigits }}">Contact the President · {{ $contactPhone }}</a>
                @else
                    <a class="contact-link" href="{{ route('gbhs.page', 'contact') }}">Contact the President</a>
                @endif
            </div>
            <p>Developed By: Sr. Engr. Tefu R. Mbole | <a href="tel:+237675321739">+237 675-321-739</a></p>
        </div>
    </div>
</footer>
<script type="application/json" id="gbhs-countries">@json(\App\Support\CountryDialCodes::picker())</script>
<script>
(function () {
    var countries = [];
    try { countries = JSON.parse(document.getElementById('gbhs-countries').textContent || '[]'); } catch (e) {}
    document.querySelectorAll('[data-cc]').forEach(function (root) {
        var hidden = root.querySelector('[data-cc-value]');
        var btn = root.querySelector('[data-cc-btn]');
        var menu = root.querySelector('[data-cc-menu]');
        var search = root.querySelector('[data-cc-search]');
        var list = root.querySelector('[data-cc-list]');
        var flag = root.querySelector('[data-cc-flag]');
        var dial = root.querySelector('[data-cc-dial]');
        function render() {
            var q = (search.value || '').toLowerCase().trim();
            var rows = !q ? countries : countries.filter(function (c) {
                return (c.name + ' ' + c.code).toLowerCase().indexOf(q) !== -1;
            });
            list.innerHTML = '';
            rows.forEach(function (c) {
                var li = document.createElement('li');
                if (c.code === hidden.value && c.name === (root.getAttribute('data-country') || 'Cameroon') && !q) {
                    li.className = 'is-on';
                }
                li.innerHTML = '<span class="flag"></span><span class="name"></span><span class="dial"></span>';
                li.querySelector('.flag').textContent = c.flag || '';
                li.querySelector('.name').textContent = c.name;
                li.querySelector('.dial').textContent = c.code;
                li.addEventListener('click', function () {
                    hidden.value = c.code;
                    flag.textContent = c.flag || '';
                    dial.textContent = c.code;
                    root.setAttribute('data-country', c.name);
                    menu.hidden = true;
                    btn.setAttribute('aria-expanded', 'false');
                    hidden.dispatchEvent(new Event('change', {bubbles: true}));
                });
                list.appendChild(li);
            });
        }
        btn.addEventListener('click', function () {
            menu.hidden = !menu.hidden;
            btn.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
            if (!menu.hidden) { render(); search.focus(); }
        });
        search.addEventListener('input', render);
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
        });
        render();
    });
})();
</script>
</body>
</html>

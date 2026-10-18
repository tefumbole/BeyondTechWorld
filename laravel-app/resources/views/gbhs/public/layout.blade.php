<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#071f13">
    <title>{{ $setting->seo_title ?? 'GBHS Kumbo Ex-Students' }}</title>
    <meta name="description" content="{{ $setting->seo_description ?? 'GBHS Kumbo Ex-Students Association' }}">
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
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 1.05rem; line-height: 1.55; color: var(--ink); background: var(--cream); overflow-x: hidden; }
        a { color: var(--leaf); }
        header { background: var(--forest-deep); color: var(--cream); position: sticky; top: 0; z-index: 40; }
        .bar { position: relative; display: flex; align-items: center; gap: 12px; padding: 8px max(12px, env(safe-area-inset-right)) 8px max(12px, env(safe-area-inset-left)); padding-top: max(8px, env(safe-area-inset-top)); width: 100%; }
        .menu-btn { display: none; }
        nav { display: flex; flex-wrap: nowrap; gap: 4px 16px; flex: 1; min-width: 0; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        nav::-webkit-scrollbar { display: none; }
        nav a { color: var(--cream); text-decoration: none; font-size: 1rem; font-weight: 600; white-space: nowrap; padding: 8px 0; }
        nav a:hover { color: var(--gold); }
        .login {
            margin-left: auto;
            color: var(--gold);
            border: 1px solid var(--gold);
            background: var(--forest-deep);
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 6px;
            padding: 8px 14px;
            white-space: nowrap;
            min-height: 44px;
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
        }
        .login:hover { background: var(--gold); color: var(--forest-deep); }
        main { padding: 0 0 48px; width: 100%; }
        .page { width: 100%; max-width: none; margin: 0; padding: 24px max(14px, env(safe-area-inset-right)) 0 max(14px, env(safe-area-inset-left)); }
        h1, h2, h3 { font-family: Georgia, "Iowan Old Style", Palatino, "Palatino Linotype", serif; color: var(--forest); line-height: 1.2; }
        h1 { font-size: clamp(1.7rem, 6vw, 2.6rem); margin: 0 0 12px; }
        h2 { font-size: clamp(1.35rem, 4vw, 1.8rem); }
        p { font-size: 1.05rem; }
        img, video, canvas { max-width: 100%; }
        .card { background: #fffdf8; border: 1px solid #e4d3a2; border-radius: 12px; padding: 18px 20px; margin: 14px 0; font-size: 1.15rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
        label { display: block; font-weight: 700; margin-top: 12px; font-size: 1.05rem; }
        input, select, textarea { width: 100%; padding: 12px 14px; border: 1px solid #d7c59a; border-radius: 8px; font-size: 16px; font-family: inherit; }
        button, .btn { background: var(--forest); color: var(--gold); border: 0; border-radius: 8px; padding: 12px 18px; text-decoration: none; display: inline-block; margin: 10px 8px 0 0; font-size: 1.05rem; font-weight: 700; cursor: pointer; min-height: 44px; touch-action: manipulation; }
        .btn.gold { background: var(--gold); color: var(--forest-deep); }
        .muted { color: #3d5346; font-size: 1rem; }
        .alert { padding: 12px 14px; border-radius: 8px; background: #fff4d2; margin-bottom: 16px; }
        img.fit { max-width: 100%; height: auto; border-radius: 12px; }
        footer { color: #f4e7c3; font-size: 1.05rem; background: #071833; }
        footer a { color: #fff; text-decoration: none; }
        .foot-band {
            position: relative;
            background: #0b3a86 url("{{ url('public/gbhs/gbhs-footer-band.jpg') }}") center / cover no-repeat;
        }
        .foot-copy {
            position: relative;
            z-index: 1;
            padding: 8px max(12px, env(safe-area-inset-right)) max(8px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left));
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 4px 16px;
            background: linear-gradient(90deg, rgba(6, 28, 92, .2), rgba(8, 36, 110, .55) 12%, rgba(8, 36, 110, .55) 88%, rgba(6, 28, 92, .2));
        }
        .foot-copy p { margin: 0; font-size: .95rem; font-weight: 600; color: #fff; }
        .contact-link { font-weight: 600; font-size: .95rem; color: #fff; }
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
            height: 100svh;
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
            object-position: center 18%;
            display: block;
        }
        @media (max-width: 800px) {
            body.home { background: var(--forest-deep); }
            body.home .hero-land { background: var(--forest-deep); }
            body.home .hero-land img {
                object-fit: contain;
                object-position: center top;
                background: var(--forest-deep);
            }
            body.home footer,
            body.home .foot-band { background: var(--forest-deep); }
            body.home .foot-copy {
                box-sizing: border-box;
                min-height: 60px;
                padding: 8px max(12px, env(safe-area-inset-right)) max(8px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left));
                flex-wrap: nowrap;
                flex-direction: column;
                align-items: flex-start;
                justify-content: center;
                gap: 2px;
                background: transparent;
            }
            body.home .foot-copy p,
            body.home .contact-link {
                font-size: clamp(11px, 3.2vw, 15px);
                line-height: 1.2;
                white-space: nowrap;
            }
        }
        body.testimonials .page { width: 100%; }
        @media (max-width: 860px) {
            .menu-btn {
                display: inline-flex;
                align-items: center;
                min-height: 44px;
                margin: 0;
                padding: 8px 14px;
                background: transparent;
                color: var(--gold);
                border: 1px solid var(--gold);
            }
            nav {
                display: none;
                position: absolute;
                left: 0;
                right: 0;
                top: 100%;
                flex-direction: column;
                gap: 0;
                padding: 4px 12px 10px;
                background: var(--forest-deep);
                box-shadow: 0 16px 24px rgba(0, 0, 0, .28);
            }
            nav.is-open { display: flex; }
            nav a { padding: 12px 4px; font-size: 1.05rem; border-bottom: 1px solid rgba(247, 243, 232, .12); }
        }
        @media (max-width: 640px) {
            .page { padding-top: 16px; }
            .card { padding: 14px; }
            .phone-row { flex-wrap: wrap; }
            .cc, .cc-btn { width: 100%; }
            .cc-menu { position: fixed; left: 12px; right: 12px; width: auto; max-height: 50vh; }
            .grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 380px) {
            nav { gap: 4px 12px; }
            nav a { font-size: .92rem; }
            .login { padding: 8px 10px; font-size: .92rem; }
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
<header>
    <div class="bar">
        <button type="button" class="menu-btn" id="gbhsMenuBtn" aria-expanded="false" aria-controls="gbhsNav">Menu</button>
        <nav id="gbhsNav">
            <a href="{{ route('gbhs.home') }}">Home</a>
            <a href="{{ route('gbhs.page', 'about') }}">About Us</a>
            <a href="{{ route('gbhs.membership', ['choose' => 1]) }}">Membership</a>
            <a href="{{ route('gbhs.page', 'leaders') }}">Testimonials</a>
            <a href="{{ route('gbhs.donate') }}">Donate</a>
            <a href="https://wa.me/237242631082" target="_blank" rel="noopener">Contact us</a>
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
    <div class="foot-band">
        <div class="foot-copy">
            <a class="contact-link" href="https://wa.me/237242631082" target="_blank" rel="noopener">Contact us: Cell: +237 242-631-082</a>
            <p><a href="https://wa.me/237675321739" target="_blank" rel="noopener">Developed By: Sr. Engr. Tefu R. Mbole | +237 675-321-739</a></p>
        </div>
    </div>
</footer>
@stack('gbhs-dial')
<script>
    (function () {
        var btn = document.getElementById('gbhsMenuBtn');
        var nav = document.getElementById('gbhsNav');
        if (!btn || !nav) return;
        btn.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = open ? 'Close' : 'Menu';
        });
    })();
</script>
</body>
</html>

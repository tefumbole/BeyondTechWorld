<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $siteLogoUrl = \App\Support\SiteBrand::logoUrl($general_setting ?? null);
        $siteTitle = \App\Support\SiteBrand::siteTitle($general_setting ?? null);
        $webUser = Auth::guard('web')->user();
        $beyondUser = Auth::guard('beyond')->user();
        $headerUser = $webUser ?: $beyondUser;
        $isAdminSession = (bool) $webUser;
        $headerName = $headerUser ? $headerUser->name : '';
        $headerRole = $isAdminSession
            ? 'ADMINISTRATOR'
            : strtoupper(str_replace('_', ' ', optional($beyondUser)->role ?: 'USER'));
        $headerInitial = $headerName !== '' ? mb_strtoupper(mb_substr($headerName, 0, 1)) : 'U';
        $shortName = \Illuminate\Support\Str::limit($headerName, 18, '…');
    @endphp
    <title>@yield('title', $siteTitle) | {{ $siteTitle }}</title>
    <meta name="description" content="@yield('meta_description', 'Beyond Enterprise — IT consultancy, networks, CCTV security, and professional sound/screen/lighting solutions.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ $siteLogoUrl }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { blue: '#003D82', dark: '#002855', light: '#0066CC', gold: '#D4AF37', navy: '#1a1a2e' },
                    },
                },
            },
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        img, video, svg { max-width: 100%; }
        main { min-width: 0; }
        @keyframes floaty { 0%,100% { transform: translateY(0); opacity:.4 } 50% { transform: translateY(-20px); opacity:.9 } }
        .floaty { animation: floaty 4s ease-in-out infinite; }
        [x-cloak] { display:none !important; }

        @keyframes navLogoSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        /* Gold ↔ Silver metallic shift (no white plate / circle) */
        @keyframes navLogoMetal {
            0%, 100% {
                filter: sepia(1) saturate(4.2) hue-rotate(2deg) brightness(1.12) contrast(1.05)
                    drop-shadow(0 0 8px rgba(212,175,55,.7));
            }
            50% {
                filter: grayscale(1) brightness(1.45) contrast(1.15) saturate(0.2)
                    drop-shadow(0 0 8px rgba(220,220,230,.65));
            }
        }
        .nav-logo-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-right: .75rem;
            background: transparent;
            border: 0;
            padding: 0;
            box-shadow: none;
        }
        .nav-logo-spin {
            width: 2.75rem;
            height: 2.75rem;
            object-fit: contain;
            background: transparent;
            border-radius: 0;
            animation: navLogoSpin 7s linear infinite, navLogoMetal 5s ease-in-out infinite;
        }
        @media (min-width: 768px) {
            .nav-logo-spin { width: 3.25rem; height: 3.25rem; }
        }
        @media (min-width: 1024px) {
            .nav-logo-spin { width: 3.5rem; height: 3.5rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .nav-logo-spin { animation: none; }
        }

        /* Homepage: one screen — nav + hero + copyright footer, no page scroll */
        body.home-lock {
            height: 100dvh;
            max-height: 100dvh;
            overflow: hidden;
        }
        body.home-lock main {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        body.home-lock .home-hero {
            flex: 1 1 auto;
            min-height: 0;
        }

        /* Transparent footer — dark text on light pages; light text on home hero */
        @keyframes beyCopyIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes beyCopyFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        @keyframes beySepPulse {
            0%, 100% { opacity: .35; }
            50% { opacity: .9; }
        }
        .bey-foot {
            position: relative;
            z-index: 80;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            flex-shrink: 0;
        }
        .bey-foot-copy {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 0.35rem 0;
            padding: 0.7rem 1rem 0.95rem;
            font-size: 0.96rem; /* +20% from 0.8rem */
            line-height: 1.45;
            text-align: center;
            background: transparent !important;
            color: #0f2744;
            text-shadow: none;
            animation: beyCopyIn .9s ease-out both, beyCopyFloat 3.6s ease-in-out .9s infinite;
        }
        .bey-foot-copy span {
            padding: 0 0.7rem;
            font-weight: 600;
            letter-spacing: .01em;
        }
        .bey-foot-copy .bey-foot-rights {
            color: #1e3a5f;
        }
        .bey-foot-copy .bey-foot-dev {
            color: #0b4f8a;
        }
        .bey-foot-copy span + span {
            border-left: 1px solid rgba(15, 39, 68, 0.28);
            animation: beySepPulse 2.8s ease-in-out infinite;
        }
        .bey-foot-copy a {
            color: #0a66c2;
            font-weight: 700;
            text-decoration: none;
            pointer-events: auto;
            text-shadow: none;
        }
        .bey-foot-copy a:hover { color: #084a8f; text-decoration: underline; }
        .bey-foot-copy .bey-foot-ver {
            color: #9a6b00;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        /* Home hero: keep light-on-dark footer */
        body.home-lock .bey-foot-copy {
            color: #f8fafc;
            text-shadow: 0 1px 2px rgba(0,0,0,.85), 0 0 12px rgba(0,20,50,.55);
        }
        body.home-lock .bey-foot-copy .bey-foot-rights,
        body.home-lock .bey-foot-copy .bey-foot-dev {
            color: #f8fafc;
        }
        body.home-lock .bey-foot-copy span + span {
            border-left-color: rgba(248, 250, 252, 0.45);
        }
        body.home-lock .bey-foot-copy a {
            color: #F5D76E;
            text-shadow: 0 1px 2px rgba(0,0,0,.9);
        }
        body.home-lock .bey-foot-copy a:hover { color: #fff; }
        body.home-lock .bey-foot-copy .bey-foot-ver {
            color: #D4AF37;
        }
        @media (max-width: 640px) {
            .bey-foot-copy { flex-direction: column; gap: 0.2rem; padding: 0.55rem 0.75rem 0.8rem; font-size: 0.9rem; /* +20% from 0.75rem */ }
            .bey-foot-copy span { padding: 0; }
            .bey-foot-copy span + span { border-left: 0; animation: none; }
        }
        body.home-lock { position: relative; }
        body.home-lock main { flex: 1 1 auto; min-height: 0; }
        body.home-lock .bey-foot {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            z-index: 80;
            margin-top: 0;
            pointer-events: none;
            background: transparent !important;
        }
        @media (max-width: 768px), (max-height: 740px) {
            body.home-lock {
                height: auto;
                max-height: none;
                overflow-x: hidden;
                overflow-y: auto;
            }
            body.home-lock main { display: block; }
            body.home-lock .bey-foot {
                position: relative;
                left: auto;
                right: auto;
                bottom: auto;
                pointer-events: auto;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .bey-foot-copy,
            .bey-foot-copy span + span { animation: none; }
        }
    </style>
    @stack('head')
</head>
<body class="bg-white text-gray-800 flex flex-col min-h-screen @yield('body_class')">

@php
    $navDefs = [
        'home'         => ['label' => 'Home', 'url' => url('/')],
        'trainings'    => ['label' => 'Training', 'url' => url('/trainings')],
        'events'       => ['label' => 'Events', 'url' => url('/events')],
        'rentals'      => ['label' => 'Rentals', 'url' => url('/rentals')],
        'apply'        => ['label' => 'Apply Now', 'url' => url('/apply-now'), 'special' => true],
        'permissions'  => ['label' => 'Permissions', 'url' => url('/permissions')],
        'about'        => ['label' => 'About Us', 'url' => url('/about')],
        'gallery'      => ['label' => 'Gallery', 'url' => url('/gallery')],
    ];
    $navLinks = [];
    foreach (\App\Support\SiteMenu::landingOrder() as $navKey) {
        // Legacy saved menus may still include removed/hidden keys
        if (in_array($navKey, ['contact', 'register', 'shareholders'], true)) {
            continue;
        }
        if (isset($navDefs[$navKey])) {
            $navLinks[] = $navDefs[$navKey];
        }
    }
    $currentUrl = url()->current();
@endphp

<header class="bg-brand-blue sticky top-0 z-40 shadow-lg" x-data="{ open: false, userMenu: false }" @keydown.escape.window="userMenu = false">
    <div class="w-full flex items-center justify-between h-14 sm:h-16 pl-1 pr-3 sm:pl-2 sm:pr-6 lg:pl-3 lg:pr-8">
        <a href="{{ url('/') }}" class="nav-logo-link" aria-label="{{ $siteTitle }} home">
            <img src="{{ $siteLogoUrl }}" alt="{{ $siteTitle }}" class="nav-logo-spin">
        </a>

        <nav class="hidden lg:flex items-center gap-x-4 xl:gap-x-6 flex-1 justify-center min-w-0">
            @foreach ($navLinks as $link)
                @php $active = rtrim($currentUrl,'/') === rtrim($link['url'],'/'); @endphp
                <a href="{{ $link['url'] }}"
                   class="text-sm xl:text-base font-medium transition-colors duration-300 whitespace-nowrap
                      @if($active) text-brand-gold border-b-2 border-brand-gold pb-1
                      @elseif(!empty($link['special'])) text-brand-gold hover:text-white font-bold
                      @else text-white hover:text-brand-gold @endif">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden lg:flex items-center gap-2 xl:gap-3 shrink-0">
            <div class="flex items-center gap-1 text-xs font-semibold">
                <span class="bg-brand-gold text-brand-blue px-2 py-1 rounded">EN</span>
                <a href="#" class="text-white hover:text-brand-gold px-2 py-1 border border-white/20 rounded">FR</a>
            </div>

            <a href="tel:+237675321739" class="text-white hover:text-brand-gold transition-colors" title="Call Us">
                <i data-lucide="phone" class="w-5 h-5"></i>
            </a>
            <a href="https://mail.hostinger.com" target="_blank" rel="noopener" class="text-white hover:text-brand-gold transition-colors" title="Webmail">
                <i data-lucide="mail" class="w-5 h-5"></i>
            </a>

            @if ($headerUser)
                <div class="relative" @click.outside="userMenu = false">
                    <button type="button" @click="userMenu = !userMenu"
                            class="flex items-center gap-2.5 pl-1 pr-1 py-1 rounded-md hover:bg-white/10 transition-colors text-left">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-brand-gold bg-gradient-to-br from-brand-gold to-brand-dark text-brand-blue font-bold text-lg">
                            {{ $headerInitial }}
                        </span>
                        <span class="hidden xl:flex flex-col leading-tight min-w-0">
                            <span class="text-white font-semibold text-sm truncate max-w-[140px]">{{ $shortName }}</span>
                            <span class="text-brand-gold text-[11px] font-bold tracking-wide uppercase">{{ $headerRole }}</span>
                        </span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-sky-200/90 shrink-0"></i>
                    </button>
                    <div x-show="userMenu" x-cloak x-transition
                         class="absolute right-0 mt-2 w-56 rounded-lg bg-white shadow-xl border border-gray-100 py-1 z-50">
                        <div class="px-4 py-2.5 text-sm font-bold text-gray-800">My Account</div>
                        <div class="border-t border-gray-100"></div>
                        @if ($isAdminSession)
                            <a href="{{ url('/admin') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-50">
                                <i data-lucide="layout-grid" class="w-4 h-4 text-gray-700"></i> Admin Dashboard
                            </a>
                            <a href="{{ url('/') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-50">
                                <i data-lucide="home" class="w-4 h-4 text-gray-700"></i> Home Page
                            </a>
                        @else
                            <a href="{{ url('/user/profile') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-800 hover:bg-gray-50">
                                <i data-lucide="user" class="w-4 h-4 text-gray-700"></i> My Profile
                            </a>
                        @endif
                        <form method="POST" action="{{ $isAdminSession ? route('logout') : route('beyond.logout') }}" @click.stop>
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ url('/login') }}" class="bg-brand-dark border border-brand-gold text-brand-gold hover:bg-brand-gold hover:text-brand-blue font-medium transition-all rounded-md px-4 py-2 flex items-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i> Login
                </a>
            @endif
        </div>

        <button @click="open = !open" class="lg:hidden text-white hover:text-brand-gold transition-colors">
            <i data-lucide="menu" class="w-6 h-6" x-show="!open"></i>
            <i data-lucide="x" class="w-6 h-6" x-show="open" x-cloak></i>
        </button>
    </div>

    <div x-show="open" x-cloak class="lg:hidden pb-4 px-4 bg-brand-blue border-t border-white/10">
        <nav class="flex flex-col space-y-3 pt-4">
            @foreach ($navLinks as $link)
                <a href="{{ $link['url'] }}" class="text-lg font-medium {{ !empty($link['special']) ? 'text-brand-gold' : 'text-white hover:text-brand-gold' }}">{{ $link['label'] }}</a>
            @endforeach
            <div class="pt-3 border-t border-white/10 space-y-2">
                @if ($headerUser)
                    <div class="flex items-center gap-3 px-1 py-2">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-brand-gold bg-brand-gold text-brand-blue font-bold">{{ $headerInitial }}</span>
                        <div>
                            <div class="text-white font-semibold text-sm">{{ $headerName }}</div>
                            <div class="text-brand-gold text-xs font-bold uppercase">{{ $headerRole }}</div>
                        </div>
                    </div>
                    @if ($isAdminSession)
                        <a href="{{ url('/admin') }}" class="flex items-center justify-center gap-2 w-full py-2 rounded bg-brand-gold text-brand-blue font-bold">Admin Dashboard</a>
                        <a href="{{ url('/') }}" class="flex items-center justify-center gap-2 w-full py-2 rounded border border-white/20 text-white">Home Page</a>
                    @else
                        <a href="{{ url('/user/profile') }}" class="flex items-center justify-center gap-2 w-full py-2 rounded bg-brand-gold text-brand-blue font-bold">My Profile</a>
                    @endif
                    <form method="POST" action="{{ $isAdminSession ? route('logout') : route('beyond.logout') }}">
                        @csrf
                        <button type="submit" class="w-full py-2 rounded border border-red-400/50 text-red-300">Logout</button>
                    </form>
                @else
                    <a href="{{ url('/login') }}" class="flex items-center justify-center gap-2 w-full py-2 rounded border border-brand-gold text-brand-gold font-medium">
                        <i data-lucide="log-in" class="w-5 h-5"></i> Login
                    </a>
                @endif
            </div>
        </nav>
    </div>
</header>

<main class="flex-1">
    @yield('content')
</main>

<footer class="bey-foot mt-auto">
    <div class="bey-foot-copy">
        <span class="bey-foot-rights">© {{ date('Y') }} Beyond Enterprise. All rights reserved.</span>
        <span class="bey-foot-dev">Developed By: Sr. Engr. Tefu R. Mbole</span>
        <span class="bey-foot-phone"><a href="https://wa.me/237675321739" target="_blank" rel="noopener">+237 675 321 739</a></span>
        <span class="bey-foot-ver">{{ \App\Support\AppVersion::bcl() }}</span>
    </div>
</footer>

@include('beyond.partials.mbole_ai_widget')

<script src="https://unpkg.com/lucide@latest"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });
    document.addEventListener('alpine:initialized', () => { if (window.lucide) lucide.createIcons(); });
</script>
@stack('scripts')
@include('components.whatsapp_phone_script')
<script>
(function () {
    if (window.__eventCountdownInit) return;
    window.__eventCountdownInit = true;
    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function bindCountdown(el) {
        if (el.__countdownBound) return;
        el.__countdownBound = true;
        var targetIso = el.getAttribute('data-target');
        if (!targetIso) return;
        var hideAfter = el.getAttribute('data-hide-after') === '1';
        var doneMsg = el.querySelector('[data-done]');
        var units = el.querySelector('[data-units]');
        var target = new Date(targetIso).getTime();
        if (isNaN(target)) return;
        function tick() {
            var diff = target - Date.now();
            if (diff <= 0) {
                if (units) units.classList.add('hidden');
                if (doneMsg) doneMsg.classList.remove('hidden');
                if (hideAfter) setTimeout(function () { el.style.display = 'none'; }, 8000);
                return false;
            }
            var secs = Math.floor(diff / 1000);
            var days = Math.floor(secs / 86400); secs %= 86400;
            var hours = Math.floor(secs / 3600); secs %= 3600;
            var mins = Math.floor(secs / 60); secs %= 60;
            var d = el.querySelector('.cd-days');
            var h = el.querySelector('.cd-hours');
            var m = el.querySelector('.cd-mins');
            var s = el.querySelector('.cd-secs');
            if (d) d.textContent = days;
            if (h) h.textContent = pad(hours);
            if (m) m.textContent = pad(mins);
            if (s) s.textContent = pad(secs);
            return true;
        }
        if (tick()) setInterval(tick, 1000);
    }
    document.querySelectorAll('[data-countdown]').forEach(bindCountdown);
})();
</script>
</body>
</html>

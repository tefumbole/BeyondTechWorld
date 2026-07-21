<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        @keyframes floaty { 0%,100% { transform: translateY(0); opacity:.4 } 50% { transform: translateY(-20px); opacity:.9 } }
        .floaty { animation: floaty 4s ease-in-out infinite; }
        [x-cloak] { display:none !important; }
    </style>
    @stack('head')
</head>
<body class="bg-white text-gray-800 flex flex-col min-h-screen">

@php
    $navDefs = [
        'home'         => ['label' => 'Home', 'url' => url('/')],
        'trainings'    => ['label' => 'Training', 'url' => url('/trainings')],
        'events'       => ['label' => 'Events', 'url' => url('/events')],
        'rentals'      => ['label' => 'Rentals', 'url' => url('/rentals')],
        'register'     => ['label' => 'Register Now', 'url' => url('/register-now')],
        'apply'        => ['label' => 'Apply Now', 'url' => url('/apply-now'), 'special' => true],
        'permissions'  => ['label' => 'Permissions', 'url' => url('/permissions')],
        'about'        => ['label' => 'About Us', 'url' => url('/about')],
        'gallery'      => ['label' => 'Gallery', 'url' => url('/gallery')],
        'shareholders' => ['label' => 'Shareholders', 'url' => url('/shareholders')],
    ];
    $navLinks = [];
    foreach (\App\Support\SiteMenu::landingOrder() as $navKey) {
        // Legacy saved menus may still include "contact" — skip; contact lives on About Us
        if ($navKey === 'contact') {
            continue;
        }
        if (isset($navDefs[$navKey])) {
            $navLinks[] = $navDefs[$navKey];
        }
    }
    $currentUrl = url()->current();
@endphp

<header class="bg-brand-blue sticky top-0 z-40 shadow-lg" x-data="{ open: false, userMenu: false }" @keydown.escape.window="userMenu = false">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <a href="{{ url('/') }}" class="flex items-center">
                <img src="{{ $siteLogoUrl }}" alt="{{ $siteTitle }}"
                     class="h-[40px] md:h-[50px] lg:h-[60px] w-auto object-contain hover:scale-105 transition-all duration-300">
            </a>

            <nav class="hidden lg:flex items-center gap-x-4 xl:gap-x-6 flex-1 justify-center">
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
                            <form method="POST" action="{{ $isAdminSession ? route('logout') : route('beyond.logout') }}">
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

        <div x-show="open" x-cloak class="lg:hidden pb-4 bg-brand-blue border-t border-white/10">
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
    </div>
</header>

<main class="flex-1">
    @yield('content')
</main>

<footer class="bg-brand-navy text-white mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <a href="{{ url('/') }}" class="inline-block mb-2">
                    <img src="{{ $siteLogoUrl }}" alt="{{ $siteTitle }}" class="h-[50px] w-auto object-contain">
                </a>
                <div class="text-2xl font-bold"><span class="text-brand-gold">{{ $siteTitle }}</span></div>
                <p class="text-gray-300 text-sm mt-4">Your Technology Bridge to Kigali. Professional IT, networking, security, and audio-visual solutions.</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-brand-gold mb-4">Quick Links</h3>
                <nav class="flex flex-col space-y-2 text-sm">
                    <a href="{{ url('/') }}" class="text-gray-300 hover:text-brand-gold">Home</a>
                    <a href="{{ url('/about') }}" class="text-gray-300 hover:text-brand-gold">About Us</a>
                    <a href="{{ url('/services') }}" class="text-gray-300 hover:text-brand-gold">Services</a>
                    <a href="{{ url('/projects') }}" class="text-gray-300 hover:text-brand-gold">Projects</a>
                    <a href="{{ url('/events') }}" class="text-gray-300 hover:text-brand-gold">Events</a>
                    <a href="{{ url('/shareholders') }}" class="text-gray-300 hover:text-brand-gold">Shareholders Portal</a>
                </nav>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-brand-gold mb-4">Contact Us</h3>
                <div class="space-y-3 text-sm">
                    <a href="https://wa.me/237675321739" target="_blank" rel="noopener" class="inline-flex items-center gap-2 bg-[#25D366] hover:bg-[#1EBE57] text-white font-semibold px-4 py-2 rounded-md">
                        <i data-lucide="message-circle" class="w-4 h-4"></i> Chat now
                    </a>
                    <a href="tel:+237675321739" class="flex items-center gap-3 text-gray-300 hover:text-brand-gold"><i data-lucide="phone" class="w-5 h-5"></i> +237 675 321 739</a>
                    <a href="mailto:info@beyondtechworld.com" class="flex items-center gap-3 text-gray-300 hover:text-brand-gold"><i data-lucide="mail" class="w-5 h-5"></i> info@beyondtechworld.com</a>
                    <a href="https://www.beyondtechworld.com" target="_blank" rel="noopener" class="flex items-center gap-3 text-gray-300 hover:text-brand-gold"><i data-lucide="globe" class="w-5 h-5"></i> www.beyondtechworld.com</a>
                </div>
            </div>
        </div>
        <div class="mt-12 pt-8 border-t border-gray-700 text-center">
            <p class="text-gray-400 text-sm">© {{ date('Y') }} Beyond Enterprise. All rights reserved.</p>
            <p class="text-gray-500 text-xs mt-2">
                Developed By: <span class="text-gray-300 font-medium">Sr. Engr. Tefu R. Mbole</span>
                <a href="https://wa.me/237675321739" target="_blank" rel="noopener" class="text-[#25D366] hover:underline font-semibold">+237675321739</a>
            </p>
            <p class="text-gray-500 text-xs mt-2">Kigali, Rwanda</p>
            <p class="text-gray-600 text-xs mt-1">{{ \App\Support\AppVersion::bcl() }}</p>
        </div>
    </div>
</footer>

<a href="https://wa.me/237675321739" target="_blank" rel="noopener"
   class="fixed bottom-6 right-6 z-50 bg-[#25D366] hover:bg-[#1EBE57] text-white rounded-full p-4 shadow-xl hover:shadow-2xl transition-all flex items-center justify-center"
   title="Chat on WhatsApp">
    <i data-lucide="message-circle" class="w-6 h-6"></i>
</a>

<script src="https://unpkg.com/lucide@latest"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });
    document.addEventListener('alpine:initialized', () => { if (window.lucide) lucide.createIcons(); });
</script>
@stack('scripts')
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

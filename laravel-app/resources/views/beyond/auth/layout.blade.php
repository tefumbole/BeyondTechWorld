@extends('beyond.layout')

@section('title', $title ?? 'Beyond Enterprise')

@push('head')
<style>
    @keyframes beyondLogoSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes beyondLogoGlow {
        0%, 100% { box-shadow: 0 0 0 6px rgba(212, 175, 55, 0.18), 0 0 28px rgba(212, 175, 55, 0.45); }
        50% { box-shadow: 0 0 0 8px rgba(212, 175, 55, 0.28), 0 0 40px rgba(212, 175, 55, 0.65); }
    }
    .beyond-logo-spin-wrap {
        width: 6.5rem;
        height: 6.5rem;
        border-radius: 9999px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: beyondLogoGlow 2.8s ease-in-out infinite;
    }
    .beyond-logo-spin {
        width: 5.75rem;
        height: 5.75rem;
        border-radius: 9999px;
        object-fit: contain;
        animation: beyondLogoSpin 6s linear infinite;
    }
    @media (prefers-reduced-motion: reduce) {
        .beyond-logo-spin, .beyond-logo-spin-wrap { animation: none; }
    }
</style>
@endpush

@section('content')
<div class="min-h-[80vh] bg-gradient-to-br from-brand-blue to-[#001f42] flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="pt-8 pb-4 px-8 text-center">
            <div class="beyond-logo-spin-wrap mx-auto mb-4">
                <img src="{{ \App\Support\SiteBrand::logoUrl($general_setting ?? null) }}" alt="{{ \App\Support\SiteBrand::siteTitle($general_setting ?? null) }}"
                     class="beyond-logo-spin">
            </div>
            @if (!empty($header))
                {!! $header !!}
            @endif
            <div class="mt-4 h-1 w-24 mx-auto rounded-full bg-brand-blue"></div>
        </div>
        <div class="px-8 pb-8 pt-2">
            @if (session('success'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif
            @yield('auth_body')
            <div class="mt-6 pt-4 border-t border-gray-100 text-center text-xs text-gray-500 leading-relaxed">
                <div class="font-bold text-brand-blue tracking-wide">{{ \App\Support\AppVersion::bcl() }}</div>
                <div class="mt-1">Developed By: <span class="font-semibold text-gray-700">Sr. Engr. Tefu R. Mbole</span></div>
            </div>
        </div>
    </div>
</div>
@endsection

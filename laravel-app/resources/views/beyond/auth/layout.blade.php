@extends('beyond.layout')

@section('title', $title ?? 'Beyond Enterprise')

@push('head')
<style>
    @keyframes beyondLogoSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .beyond-logo-spin { animation: beyondLogoSpin 6s linear infinite; }
    .beyond-logo-ring {
        background: conic-gradient(from 0deg, #D4AF37, #003D82, #0066CC, #D4AF37);
        animation: beyondLogoSpin 8s linear infinite;
    }
</style>
@endpush

@section('content')
<div class="min-h-[80vh] bg-gradient-to-br from-brand-blue to-[#001f42] flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="pt-8 pb-4 px-8 text-center">
            <div class="relative mx-auto mb-4 h-24 w-24">
                <div class="beyond-logo-ring absolute inset-0 rounded-full"></div>
                <div class="absolute inset-[3px] rounded-full bg-white flex items-center justify-center overflow-hidden">
                    <img src="/branding/beyond-logo.png" alt="Beyond Enterprise"
                         class="beyond-logo-spin h-[80%] w-[80%] object-contain">
                </div>
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
        </div>
    </div>
</div>
@endsection

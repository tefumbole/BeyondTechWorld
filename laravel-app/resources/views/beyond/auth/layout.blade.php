@extends('beyond.layout')

@section('title', $title ?? 'Beyond Enterprise')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-brand-blue to-[#001f42] flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden">
        @if (!empty($header))
            <div class="bg-brand-gold p-6 text-center">
                {!! $header !!}
            </div>
        @endif
        <div class="p-8">
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

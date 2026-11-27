@extends('layout.main')

@section('content')
@php
    $anTab = 'announcements.compose';
    $mai = \App\Support\MaiHost::is();
@endphp
<section class="forms" style="background:#e8eef8; min-height:100vh; padding:18px 0 40px;">
    <div class="container-fluid an-shell an-compose {{ $mai ? 'is-mai' : '' }}" style="max-width:1180px;">
        <header class="an-topbar">
            <div>
                <h1>Compose announcement</h1>
                <p>{{ $mai ? 'Send from your linked WhatsApp' : 'Send a WhatsApp announcement to customers and staff' }}</p>
            </div>
            <nav>
                @if($mai)
                    <a href="{{ route('whatsapp.chats') }}">Back to chats</a>
                @else
                    @if(Route::has('setting.createSms'))<a href="{{ route('setting.createSms') }}">Create SMS</a>@endif
                    @if(Route::has('setting.messaging'))<a href="{{ route('setting.messaging') }}">SMS settings</a>@endif
                    <a href="{{ route('announcements.settings') }}">Settings</a>
                @endif
            </nav>
        </header>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @include('announcement_manager.partials.composer')
    </div>
</section>
<style>
    .an-topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; background:linear-gradient(100deg,#072f6b,#0b3f90 60%,#1456b8); color:#fff; border-radius:22px; padding:18px 22px; margin:0 0 16px; box-shadow:0 16px 40px rgba(11,63,144,.25); }
    .an-topbar h1 { margin:0; font-size:22px; font-weight:800; color:#fff; }
    .an-topbar p { margin:4px 0 0; color:#d6e2f7; font-size:13px; }
    .an-topbar nav { display:flex; gap:8px; flex-wrap:wrap; }
    .an-topbar a { color:#fff; border:1px solid rgba(255,255,255,.35); border-radius:999px; padding:8px 14px; font-size:13px; font-weight:700; text-decoration:none; }
    body.mai-announce .header,
    body.mai-announce nav.side-navbar,
    body.mai-announce .sidebar-backdrop,
    body.mai-announce .main-footer,
    body.mai-announce #beyond-module-tabs { display:none !important; }
    body.mai-announce .page { margin:0 !important; width:100% !important; }
    @media (max-width: 900px) {
        .forms { padding: 8px 0 24px !important; }
        .an-shell.an-compose { padding-left: 10px; padding-right: 10px; }
        .an-topbar { flex-direction: column; align-items: stretch; padding: 14px; border-radius: 16px; }
        .an-topbar h1 { font-size: 20px; }
        .an-topbar nav a { min-height: 40px; display: inline-flex; align-items: center; }
    }
</style>
@endsection

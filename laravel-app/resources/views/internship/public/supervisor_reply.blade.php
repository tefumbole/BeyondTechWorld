@extends('beyond.auth.layout')

@section('title', 'Reply to intern')

@php
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Reply to intern</h1>'
        .'<p class="auth-sub">Beyond Enterprise internship message</p>';
@endphp

@push('head')
<style>
    .auth-card { max-width: 560px; text-align: left; }
    .auth-logo-ring { margin-left: auto; margin-right: auto; }
    .sr-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; margin-bottom:14px; }
    .sr-label { font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
    .sr-body { white-space:pre-wrap; color:#0f172a; margin:0; }
    .sr-form textarea { width:100%; min-height:140px; border:1px solid #dbe3ef; border-radius:10px; padding:10px 12px; font:inherit; }
    .sr-btn { display:inline-flex; align-items:center; justify-content:center; width:100%; margin-top:12px; background:#0b3f90; color:#fff; border:0; border-radius:10px; padding:12px 16px; font-weight:700; cursor:pointer; }
    .sr-btn:hover { background:#083272; }
</style>
@endpush

@section('auth_body')
    @if(session('message'))
        <div class="auth-alert auth-alert-ok">{{ session('message') }}</div>
    @endif
    @if(session('not_permitted'))
        <div class="auth-alert auth-alert-error">{{ session('not_permitted') }}</div>
    @endif

    <div class="sr-box">
        <div class="sr-label">From intern</div>
        <p class="mb-1" style="margin:0 0 6px;font-weight:700;color:#0b3f90;">{{ optional($student)->name ?: 'Intern' }}</p>
        <p class="sr-body">{{ $message->body }}</p>
        <p class="auth-sub" style="margin-top:8px;margin-bottom:0;">Sent {{ optional($message->created_at)->format('D d M Y H:i') }}</p>
    </div>

    @if($message->isReplied())
        <div class="sr-box" style="background:#ecfdf5;border-color:#a7f3d0;">
            <div class="sr-label">Your reply</div>
            <p class="sr-body">{{ $message->reply_body }}</p>
            <p class="auth-sub" style="margin-top:8px;margin-bottom:0;">Sent {{ optional($message->replied_at)->format('D d M Y H:i') }}. The intern already received a WhatsApp copy.</p>
        </div>
    @else
        <form method="POST" action="{{ route('internship.supervisor.reply.store', $message->reply_token) }}" class="sr-form">
            @csrf
            <label class="sr-label" for="reply">Your reply</label>
            <textarea id="reply" name="reply" required maxlength="2000" placeholder="Write your reply to the intern…">{{ old('reply') }}</textarea>
            <p class="auth-sub" style="margin-top:8px;">The intern receives this on WhatsApp, and you receive a copy too. Do not reply in the WhatsApp chat.</p>
            <button type="submit" class="sr-btn">Send reply</button>
        </form>
    @endif
@endsection

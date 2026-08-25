@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <h1 class="ip-title"><i class="dripicons-message"></i> Message supervisor</h1>
        <p class="ip-meta mb-3">
            Send a message from this portal. A WhatsApp copy goes to you and to your supervisor.
            They reply from a private link; you both receive a WhatsApp copy of that reply.
        </p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @include('internship.student.partials.student-nav', ['activeNav' => 'messages', 'hideOpenTask' => true])

        @if(!$enrolment)
            <div class="ip-card">
                <p class="mb-0 text-muted">You need an internship enrolment before you can message a supervisor.</p>
            </div>
        @else
            @include('internship.student.partials.supervisors', ['supervisors' => $supervisors ?? []])

            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Write a message</h5>
                @php
                    $reachable = collect($supervisors ?? [])->filter(function ($row) {
                        return strlen(preg_replace('/\D+/', '', (string) ($row['phone'] ?? ''))) >= 8;
                    })->values();
                    $studentPhone = preg_replace('/\D+/', '', (string) (Auth::user()->phone ?: (Auth::user()->additional_phone ?? '')));
                @endphp
                @if(strlen($studentPhone) < 8)
                    <div class="alert alert-warning">Add a WhatsApp number on <a href="{{ url('/user/profile') }}">My Profile</a> so a copy of each message can be sent to you.</div>
                @endif
                @if($reachable->isEmpty())
                    <p class="mb-0 text-muted">Your supervisor has no WhatsApp number on file yet. Ask an Internship Administrator to add one.</p>
                @else
                    <form method="POST" action="{{ route('internship.student.messages.send') }}">
                        @csrf
                        <div class="form-group">
                            <label>Supervisor</label>
                            <select name="supervisor_phone" class="form-control" required>
                                @foreach($reachable as $s)
                                    @php $digits = preg_replace('/\D+/', '', $s['phone']); @endphp
                                    <option value="{{ $s['phone'] }}" {{ (string) $selectedPhone === (string) $digits || old('supervisor_phone') === $s['phone'] ? 'selected' : '' }}>
                                        {{ $s['name'] }}{{ !empty($s['source']) ? ' — '.$s['source'] : '' }} ({{ $s['phone'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="body" class="form-control" rows="5" maxlength="2000" required placeholder="Write your message to the supervisor…">{{ old('body') }}</textarea>
                        </div>
                        <button type="submit" class="ip-btn"><i class="dripicons-export"></i> Send via WhatsApp</button>
                    </form>
                @endif
            </div>

            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Recent messages</h5>
                @forelse($threads as $thread)
                    <div style="padding:.85rem 0;border-top:1px solid #eef2f7;">
                        <div class="ip-meta">
                            To {{ $thread->supervisor_name ?: 'Supervisor' }}
                            · {{ optional($thread->created_at)->format('D d M Y H:i') }}
                            ·
                            @if($thread->isReplied())
                                <span class="ip-badge active">Replied</span>
                            @else
                                <span class="ip-badge warn">Awaiting reply</span>
                            @endif
                        </div>
                        <p class="mb-1 mt-1" style="white-space:pre-wrap;">{{ $thread->body }}</p>
                        @if($thread->isReplied())
                            <div class="ip-grade-box mt-2">
                                <div class="ip-meta">Supervisor reply · {{ optional($thread->replied_at)->format('D d M Y H:i') }}</div>
                                <p class="mb-0 mt-1" style="white-space:pre-wrap;">{{ $thread->reply_body }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="mb-0 text-muted">No messages yet. Send the first one above.</p>
                @endforelse
            </div>
        @endif
    </div>
</section>
@endsection

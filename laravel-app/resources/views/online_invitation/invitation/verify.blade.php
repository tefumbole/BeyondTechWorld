@extends('beyond.layout')

@section('title', 'Invitation Verification')
@section('meta_description', 'Verify a Beyond Tech World digital invitation.')

@section('content')
@php
    $event = $data->event ?? null;
    $categoryName = optional($data->category)->name ?: 'Guest';
    $recipientName = $data->recipient_name
        ?: (optional($data->customer)->name ?: (optional($data->user)->name ?: 'Guest'));
    $eventName = optional($event)->name ?: 'Event';
    $location = optional($event)->location;
    $eventAtText = optional($event)->event_at;
    try {
        if ($eventAtText) {
            $eventAtText = \Carbon\Carbon::parse($eventAtText)->format('D, M d, Y h:i A');
        }
    } catch (\Throwable $e) {
    }
    $isUsed = ! empty($data->used_at);
    $isFailed = ($data->status ?? '') === 'failed';
    if ($isUsed) {
        $statusLabel = 'Already admitted';
        $statusTone = 'amber';
    } elseif ($isFailed) {
        $statusLabel = 'Invalid';
        $statusTone = 'red';
    } else {
        $statusLabel = 'Valid';
        $statusTone = 'green';
    }
@endphp
<div class="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center p-4 py-16">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <div class="text-center px-6 pt-8 pb-4">
            @if ($statusTone === 'green')
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="check-circle" class="w-9 h-9 text-green-600"></i>
                </div>
            @elseif ($statusTone === 'amber')
                <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="badge-check" class="w-9 h-9 text-amber-600"></i>
                </div>
            @else
                <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="x-circle" class="w-9 h-9 text-red-500"></i>
                </div>
            @endif
            <h1 class="text-xl font-bold text-brand-blue">Digital Invitation</h1>
            <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold
                @if($statusTone === 'green') text-green-700 bg-green-50 border-green-200
                @elseif($statusTone === 'amber') text-amber-800 bg-amber-50 border-amber-200
                @else text-red-700 bg-red-50 border-red-200
                @endif
                border rounded-full px-3 py-1">
                Status: {{ $statusLabel }}
            </p>
        </div>

        <div class="px-6 pb-6 text-sm">
            @if(session('message'))
                <div class="mb-3 rounded-lg bg-green-50 border border-green-200 text-green-800 px-3 py-2 text-center">{{ session('message') }}</div>
            @endif
            @if(session('not_permitted'))
                <div class="mb-3 rounded-lg bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-center">{{ session('not_permitted') }}</div>
            @endif

            <div class="flex justify-between border-b py-2.5">
                <span class="text-gray-500">Guest</span>
                <span class="font-semibold text-gray-900 text-right">{{ $recipientName }}</span>
            </div>
            <div class="flex justify-between border-b py-2.5">
                <span class="text-gray-500">Type</span>
                <span class="font-semibold text-gray-900 text-right">{{ $categoryName }}</span>
            </div>
            <div class="flex justify-between border-b py-2.5">
                <span class="text-gray-500">Event</span>
                <span class="font-semibold text-gray-900 text-right">{{ $eventName }}</span>
            </div>
            <div class="flex justify-between border-b py-2.5">
                <span class="text-gray-500">Date</span>
                <span class="text-gray-800 text-right">{{ $eventAtText ?: '—' }}</span>
            </div>
            @if($location)
                <div class="flex justify-between border-b py-2.5">
                    <span class="text-gray-500">Location</span>
                    <span class="text-gray-800 text-right">{{ $location }}</span>
                </div>
            @endif
            @if($isUsed)
                <div class="flex justify-between py-2.5">
                    <span class="text-gray-500">Admitted at</span>
                    <span class="text-gray-800 text-right">{{ $data->used_at }}</span>
                </div>
            @endif

            @if(!empty($canRsvp) && empty($data->used_at) && empty($canManage))
                <div class="mt-5 text-center">
                    <p class="text-gray-600 mb-3">Will you attend?</p>
                    @if(($data->rsvp_status ?? 'pending') === 'accepted')
                        <span class="inline-block rounded-full bg-green-100 text-green-800 text-xs font-semibold px-3 py-1">You accepted — attending</span>
                    @elseif(($data->rsvp_status ?? '') === 'declined')
                        <span class="inline-block rounded-full bg-gray-100 text-gray-700 text-xs font-semibold px-3 py-1">You declined</span>
                    @else
                        <form action="{{ route('online_invitation.invite.rsvp_accept', $data->token) }}" method="POST" class="inline-block m-1">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg bg-green-600 text-white font-semibold text-sm">Accept</button>
                        </form>
                        <form action="{{ route('online_invitation.invite.rsvp_decline', $data->token) }}" method="POST" class="inline-block m-1">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-semibold text-sm">Decline</button>
                        </form>
                    @endif
                </div>
            @endif

            @if(!empty($canManage))
                <div class="mt-5 text-center border-t pt-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500 mb-2 font-semibold">Staff check-in</p>
                    @if(!$isUsed)
                        <form action="{{ route('online_invitation.invite.accept_use', $data->token) }}" method="POST" onsubmit="return confirm('Admit this guest into the hall?')">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-brand-blue text-white font-semibold">Admit guest</button>
                        </form>
                    @else
                        <span class="inline-block rounded-full bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1">Already admitted</span>
                    @endif
                    <a href="{{ route('online_invitation.invitations.index') }}" class="inline-block mt-3 text-sm text-brand-blue underline">Back to invitations</a>
                </div>
            @endif

            <p class="text-center text-xs text-gray-400 pt-4 mt-4 border-t">Beyond Tech World · Invitation Verification</p>
        </div>
    </div>
</div>
@endsection

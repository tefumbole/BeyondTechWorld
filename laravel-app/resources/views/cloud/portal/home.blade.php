@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>{{ $tenant->name }}</h1>
    <p class="muted">{{ $tenant->system_name }}</p>
    @if($heroUrl)
        <img class="hero" src="{{ $heroUrl }}" alt="Company hero">
    @else
        <p class="muted">No hero image yet. Add one in Settings.</p>
    @endif
</div>
<div class="card">
    <h2>Subscriptions</h2>
    @forelse($subscriptions as $subscription)
        @php
            $zone = $tenant->timezone ?: 'Africa/Douala';
            $trialLeft = null;
            if ($subscription->status === 'TRIALING' && $subscription->trial_ends_at) {
                $seconds = $subscription->trial_ends_at->getTimestamp() - time();
                if ($seconds > 0) {
                    $trialLeft = intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m remaining';
                }
            }
        @endphp
        <p>
            <strong>{{ $subscription->plan ? $subscription->plan->name : 'Module' }}</strong>
            — {{ $subscription->status }}
            @if($subscription->trial_ends_at)
                <span class="muted">trial ends {{ $subscription->trial_ends_at->copy()->timezone($zone)->format('Y-m-d H:i') }}</span>
            @endif
            @if($trialLeft)
                <span class="muted">{{ $trialLeft }}</span>
            @endif
            @if($subscription->current_period_end && $subscription->status !== 'TRIALING')
                <span class="muted">renews or ends {{ $subscription->current_period_end->copy()->timezone($zone)->format('Y-m-d H:i') }}</span>
            @endif
            @if($subscription->quoted_price)
                <span class="muted">Quoted {{ number_format((float) $subscription->quoted_price, 0) }} {{ $subscription->quoted_currency }}</span>
            @endif
        </p>
        @if(in_array($subscription->status, ['TRIALING', 'PAST_DUE', 'EXPIRED', 'CANCELLED'], true))
            <p><a class="btn" href="{{ route('cloud.subscribe') }}">Renew</a></p>
        @endif
    @empty
        <p class="muted">You have not started a module yet.</p>
    @endforelse
    <a class="btn" href="{{ route('cloud.subscribe') }}">Choose a module</a>
</div>
@endsection

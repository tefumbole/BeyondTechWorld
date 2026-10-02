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
        <p>
            <strong>{{ $subscription->plan ? $subscription->plan->name : 'Module' }}</strong>
            — {{ $subscription->status }}
            @if($subscription->trial_ends_at)
                until {{ $subscription->trial_ends_at->format('Y-m-d H:i') }}
            @endif
            @if($subscription->quoted_price)
                <span class="muted">Quoted {{ number_format((float) $subscription->quoted_price, 0) }} {{ $subscription->quoted_currency }}</span>
            @endif
        </p>
    @empty
        <p class="muted">You have not started a module yet.</p>
    @endforelse
    <a class="btn" href="{{ route('cloud.subscribe') }}">Choose a module</a>
</div>
@endsection

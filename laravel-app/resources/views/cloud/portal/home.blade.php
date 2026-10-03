@extends('cloud.portal.layout')
@section('content')
<p class="banner">{{ $welcome }}</p>
<div class="card">
    <p class="stage-kicker">Stage 1 · Company</p>
    <h1>Welcome to {{ $tenant->name }}</h1>
    <p class="muted">{{ $tenant->system_name }}</p>
    @if($subscriptions->isNotEmpty())
        <p><strong>Selected services</strong></p>
        <div class="svc-grid">
            @foreach($subscriptions as $subscription)
                @php $code = $subscription->plan && $subscription->plan->module ? $subscription->plan->module->code : ''; @endphp
                <article class="svc svc-{{ $code }}">
                    <strong>{{ $subscription->plan ? $subscription->plan->name : 'Service' }}</strong>
                    <span>{{ $subscription->status }}</span>
                    @if($subscription->quoted_price)
                        <span class="muted"> · {{ number_format((float) $subscription->quoted_price, 0) }} {{ $subscription->quoted_currency }}</span>
                    @endif
                </article>
            @endforeach
        </div>
        @php
            $trialEnd = $subscriptions->where('status', 'TRIALING')->sortBy('trial_ends_at')->first();
            $zone = $tenant->timezone ?: 'Africa/Douala';
        @endphp
        @if($trialEnd && $trialEnd->trial_ends_at)
            <p>Trial ends {{ $trialEnd->trial_ends_at->copy()->timezone($zone)->format('Y-m-d H:i') }} ({{ $zone }}).</p>
        @endif
    @endif
    <p><a class="btn" href="{{ route('cloud.settings') }}">Continue setup</a></p>
    @if($tenant->status === 'SUSPENDED')
        <p>This company is suspended. Records stay available to view.</p>
    @endif
    @if(isset($memberships) && $memberships->count() > 1)
        <form method="POST" action="{{ route('cloud.company.switch') }}">
            @csrf
            <label>Company</label>
            <select name="cloud_tenant_id">
                @foreach($memberships as $membership)
                    <option value="{{ $membership->cloud_tenant_id }}" {{ (int) $membership->cloud_tenant_id === (int) $tenant->id ? 'selected' : '' }}>{{ $membership->cloudTenant ? $membership->cloudTenant->name : 'Company' }}</option>
                @endforeach
            </select>
            <button type="submit">Switch company</button>
        </form>
    @endif
    @if($heroUrl)
        <img class="hero" src="{{ $heroUrl }}" alt="Company hero">
    @else
        <p class="muted">No hero image yet. Add one in Settings.</p>
    @endif
</div>
<div class="card">
    <p class="stage-kicker">Stage 2 · Subscriptions</p>
    <h2>Subscriptions</h2>
    <div class="svc-grid">
    @forelse($subscriptions as $subscription)
        @php
            $zone = $tenant->timezone ?: 'Africa/Douala';
            $code = $subscription->plan && $subscription->plan->module ? $subscription->plan->module->code : '';
            $trialLeft = null;
            if ($subscription->status === 'TRIALING' && $subscription->trial_ends_at) {
                $seconds = $subscription->trial_ends_at->getTimestamp() - time();
                if ($seconds > 0) {
                    $trialLeft = intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m remaining';
                }
            }
        @endphp
        <article class="svc svc-{{ $code }}">
            <strong>{{ $subscription->plan ? $subscription->plan->name : 'Module' }}</strong>
            <span>{{ $subscription->status }}</span>
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
            @if(in_array($subscription->status, ['EXPIRED', 'PAST_DUE', 'CANCELLED'], true))
                <p>{{ $ended }}</p>
            @endif
        </article>
    @empty
        <p class="muted">You have not started a module yet.</p>
    @endforelse
    </div>
    <p>
        <a class="btn" href="{{ route('cloud.subscribe') }}">Choose a module</a>
        <a class="btn alt" href="{{ route('cloud.messaging') }}">Messaging</a>
    </p>
</div>
<div class="card">
    <p class="stage-kicker">Stage 3 · Setup</p>
    <h2>Setup</h2>
    <p class="muted">Sample business records are not added. A company starts empty.</p>
    @foreach($checklist as $step)
        @if($step['show'])
            <p class="check {{ $step['done'] ? 'done' : '' }}">{{ $step['done'] ? 'Done' : 'To do' }} — {{ $step['label'] }}</p>
        @endif
    @endforeach
</div>
<input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
@endsection

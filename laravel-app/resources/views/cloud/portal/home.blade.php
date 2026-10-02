@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Welcome to {{ $tenant->name }}</h1>
    <p class="muted">{{ $tenant->system_name }}</p>
    <p>{{ $welcome }}</p>
    @if($subscriptions->isNotEmpty())
        <p><strong>Selected services</strong></p>
        <ul>
            @foreach($subscriptions as $subscription)
                <li>{{ $subscription->plan ? $subscription->plan->name : 'Service' }} — {{ $subscription->status }}</li>
            @endforeach
        </ul>
        @php
            $trialEnd = $subscriptions->where('status', 'TRIALING')->sortBy('trial_ends_at')->first();
            $zone = $tenant->timezone ?: 'Africa/Douala';
        @endphp
        @if($trialEnd && $trialEnd->trial_ends_at)
            <p>Trial ends {{ $trialEnd->trial_ends_at->copy()->timezone($zone)->format('Y-m-d H:i') }} ({{ $zone }}).</p>
        @endif
    @endif
    <a class="btn" href="{{ route('cloud.settings') }}">Continue setup</a>
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
        @if(in_array($subscription->status, ['EXPIRED', 'PAST_DUE', 'CANCELLED'], true))
            <p>{{ $ended }}</p>
            <p class="muted">{{ $paymentNotice }}</p>
        @endif
    @empty
        <p class="muted">You have not started a module yet.</p>
    @endforelse
    <a class="btn" href="{{ route('cloud.subscribe') }}">Choose a module</a>
    <a class="btn" href="{{ route('cloud.messaging') }}">Messaging</a>
</div>
<div class="card">
    <h2>Setup</h2>
    <p class="muted">Sample business records are not added. A company starts empty.</p>
    <ul>
        @foreach($checklist as $step)
            @if($step['show'])
                <li>{{ $step['done'] ? 'Done' : 'To do' }} — {{ $step['label'] }}</li>
            @endif
        @endforeach
    </ul>
</div>
<div class="card">
    <h2>Add another company</h2>
    <form method="POST" action="{{ route('cloud.companies.store') }}">
        @csrf
        <input type="hidden" name="onboard_token" value="{{ $onboardToken }}">
        @foreach($plans as $plan)
            <label><input type="checkbox" name="modules[]" value="{{ $plan->module->code }}"> {{ $plan->name }} — {{ number_format((float) $plan->price, 0) }} {{ $plan->currency }}</label>
        @endforeach
        <label>Company name</label>
        <input name="company_name" required>
        <button type="submit">Create company</button>
    </form>
</div>
@endsection

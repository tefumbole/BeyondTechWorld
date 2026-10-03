@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <p class="stage-kicker">Subscriptions</p>
    <h1>Subscribe</h1>
    <p class="muted">Each module has its own trial. Your phone number can take that trial once. The price shown is the current database price. Activation after the trial is arranged with BeyondTechWorld.</p>
    <div class="svc-grid">
    @foreach($plans as $plan)
        @php
            $current = $subscriptions->get($plan->id);
            $code = $plan->module ? $plan->module->code : '';
        @endphp
        <article class="svc svc-{{ $code }}">
            <h2>{{ $plan->name }}</h2>
            <p>{{ $plan->module ? $plan->module->description : '' }}</p>
            <p><strong>{{ number_format((float) $plan->price, 0) }} {{ $plan->currency }}</strong> / {{ strtolower($plan->billing_interval ?: 'month') }}</p>
            <p class="muted">Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</p>
            @if($current)
                <p>Status: {{ $current->status }}</p>
            @else
                <form method="POST" action="{{ route('cloud.trial', $plan->id) }}">
                    @csrf
                    <button type="submit">Start trial</button>
                </form>
            @endif
        </article>
    @endforeach
    </div>
</div>
@endsection
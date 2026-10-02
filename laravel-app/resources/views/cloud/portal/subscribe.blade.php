@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Subscribe</h1>
    <p class="muted">Each module has its own trial. Your phone number can take that trial once. After the trial, pay with MoMo or VISA. The price shown is the current database price.</p>
    @foreach($plans as $plan)
        @php $current = $subscriptions->get($plan->id); @endphp
        <div class="card">
            <h2>{{ $plan->name }}</h2>
            <p>{{ $plan->module ? $plan->module->description : '' }}</p>
            <p><strong>{{ number_format((float) $plan->price, 0) }} {{ $plan->currency }}</strong> / {{ strtolower($plan->billing_interval ?: 'month') }}</p>
            <p class="muted">Trial: {{ (int) $plan->trial_value }} {{ strtolower($plan->trial_unit) }}{{ (int) $plan->trial_value === 1 ? '' : 's' }}</p>
            @if($current)
                <p>Status: {{ $current->status }}</p>
                @foreach($methods as $method)
                    <form method="POST" action="{{ route('cloud.pay', $current->id) }}" style="display:inline-block;margin-right:8px;">
                        @csrf
                        <input type="hidden" name="method" value="{{ $method->code }}">
                        <button type="submit">Pay with {{ $method->name }}</button>
                    </form>
                @endforeach
            @else
                <form method="POST" action="{{ route('cloud.trial', $plan->id) }}">
                    @csrf
                    <button type="submit">Start trial</button>
                </form>
            @endif
        </div>
    @endforeach
</div>
@endsection

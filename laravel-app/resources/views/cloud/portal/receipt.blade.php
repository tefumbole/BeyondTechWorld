@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Beyond Cloud receipt</h1>
    <p>From: BeyondTechWorld / Beyond Enterprise</p>
    <p>To: {{ $tenant->name }}</p>
    <p>Date: {{ $payment->paid_at }}</p>
    <p>Reference: {{ $payment->internal_reference ?: $payment->provider_reference }}</p>
    <p>Status: {{ $payment->status }}</p>
    <p><strong>{{ number_format((float) $payment->amount, 0) }} {{ $payment->currency }}</strong></p>
    @if($payment->items && count($payment->items))
        <ul>
        @foreach($payment->items as $item)
            <li>
                {{ $item->module_code }}
                — {{ number_format((float) $item->amount, 0) }} {{ $item->currency }}
                @if($item->subscription)
                    — service through {{ $item->subscription->current_period_end }}
                @endif
            </li>
        @endforeach
        </ul>
    @elseif($payment->subscription)
        <p>{{ $payment->subscription->plan ? $payment->subscription->plan->name : 'Subscription' }} through {{ $payment->subscription->current_period_end }}</p>
    @endif
</div>
@endsection

@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Billing</h1>
    <p class="muted">These are payments from {{ $tenant->name }} to BeyondTechWorld for Beyond Cloud. They are not your customer invoices.</p>
    @forelse($payments as $payment)
        <div class="card">
            <p>{{ $payment->created_at }}</p>
            <p>{{ number_format((float) $payment->amount, 0) }} {{ $payment->currency }}</p>
            <p>Status: {{ $payment->status === 'PENDING' ? 'Payment Pending' : $payment->status }}</p>
            <p>{{ $payment->method_code }} / {{ $payment->provider }}</p>
            <p>Reference: {{ $payment->internal_reference ?: $payment->id }}</p>
            @if($payment->items && count($payment->items))
                <ul>
                @foreach($payment->items as $item)
                    <li>{{ $item->module_code }} — {{ number_format((float) $item->amount, 0) }} {{ $item->currency }}</li>
                @endforeach
                </ul>
            @endif
            @if($payment->status === 'PAID')
                <p><a href="{{ route('cloud.billing.receipt', $payment->id) }}">Receipt</a></p>
            @endif
        </div>
    @empty
        <p>No subscription payments yet.</p>
    @endforelse
</div>
@endsection

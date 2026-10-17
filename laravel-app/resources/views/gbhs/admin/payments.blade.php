@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Live payments</h3>
    @include('gbhs.admin.nav')
    @foreach($payments as $payment)
        <div class="border-bottom py-2">
            {{ $payment->created_at }} · {{ $payment->donor_name }} · {{ number_format($payment->amount) }} · {{ $payment->method }} · {{ $payment->status }}
            @if($payment->method === 'bank' && $payment->status !== 'paid')
                <form method="post" action="{{ route('gbhs.payments.bank', $payment->id) }}" style="display:inline">@csrf<button class="btn btn-sm btn-primary">Record as received</button></form>
            @endif
        </div>
    @endforeach
</div></section>
@endsection

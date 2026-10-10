@if(Auth::check() && in_array((int) Auth::user()->role_id, [1, 2], true))
    <div class="btn-group mb-3" role="group">
        <a class="btn {{ $tab === 'payment' ? 'btn-primary' : 'btn-default' }}" href="{{ route('payment.index') }}">Payment</a>
        <a class="btn {{ $tab === 'payout' ? 'btn-primary' : 'btn-default' }}" href="{{ route('payout.index') }}">Payout</a>
        <a class="btn {{ $tab === 'request' ? 'btn-primary' : 'btn-default' }}" href="{{ route('payout.request') }}">Request for Payment</a>
    </div>
@endif

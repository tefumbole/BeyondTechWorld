@if(Auth::check() && in_array((int) Auth::user()->role_id, [1, 2], true))
    @php $tab = isset($tab) ? $tab : ''; @endphp
    <div class="btn-group mb-3" role="group" style="display:flex;flex-wrap:wrap">
        <a class="btn {{ $tab === 'payment' ? 'btn-primary' : 'btn-default' }}" href="{{ route('payment.index') }}">Payment</a>
        <a class="btn {{ $tab === 'payout' ? 'btn-primary' : 'btn-default' }}" href="{{ route('payout.index') }}">Payout</a>
        <a class="btn {{ $tab === 'request' ? 'btn-primary' : 'btn-default' }}" href="{{ route('payout.request') }}">Request for Payment</a>
        <a class="btn {{ $tab === 'donations' ? 'btn-primary' : 'btn-default' }}" href="{{ route('donations.index') }}">Donations</a>
        <a class="btn {{ $tab === 'deposits' ? 'btn-primary' : 'btn-default' }}" href="{{ route('deposit.index') }}">Deposits</a>
    </div>
@endif

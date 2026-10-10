@extends('beyond.layout')

@section('title', 'Donation')

@section('content')
<section class="py-10 sm:py-14 bg-slate-50">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl font-extrabold text-brand-blue m-0">Donation</h1>
            <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
            <p class="text-slate-700 m-0">{{ $donation->person_name }}</p>
            <p class="text-3xl font-extrabold text-brand-blue my-3">{{ number_format($donation->amount, 0, '.', ' ') }} XAF</p>
            @if($donation->note)<p class="text-slate-600">{{ $donation->note }}</p>@endif
            @if(session('not_permitted'))
                <div class="rounded-lg bg-red-50 text-red-800 px-3 py-2 text-sm">{{ session('not_permitted') }}</div>
            @elseif($donation->status === 'paid')
                <div class="rounded-lg bg-green-50 text-green-800 px-3 py-2">Your donation has been received. Thank you.</div>
            @elseif($donation->status === 'failed')
                <div class="rounded-lg bg-red-50 text-red-800 px-3 py-2">{{ $donation->error ?: 'The donation was not approved.' }}</div>
                <a class="inline-block mt-4 text-brand-blue font-bold" href="{{ route('donate.show') }}">Try again</a>
            @else
                <div class="rounded-lg bg-amber-50 text-amber-900 px-3 py-2" id="waitNote">
                    @if($donation->method === 'crypto')
                        Send the exact USDT amount below. This page updates when Binance receives it.
                    @elseif($donation->method === 'visa')
                        Open the VISA page and complete the card payment.
                    @else
                        Approve the prompt on your phone. MTN and Orange both use this step.
                    @endif
                </div>
                @php $crypto = ($donation->method === 'crypto') ? json_decode((string) $donation->payment_link, true) : null; @endphp
                @if(is_array($crypto) && ! empty($crypto['address']))
                    <p class="mt-4 mb-1 text-sm text-slate-600">Send exactly</p>
                    <p class="text-2xl font-extrabold text-brand-blue m-0">{{ $crypto['usdt'] }} {{ $crypto['coin'] }}</p>
                    <p class="mt-3 mb-1 text-sm font-semibold">Network: {{ $crypto['network'] }}</p>
                    <p class="text-sm text-slate-600 m-0">Use this network only. A transfer on another network is not received.</p>
                    <p id="cryptoAddress" class="mt-3 break-all font-mono text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">{{ $crypto['address'] }}</p>
                    <button type="button" id="copyAddress" class="mt-3 w-full rounded-full border border-brand-blue text-brand-blue font-bold py-3 bg-white">Copy address</button>
                @elseif($donation->payment_link)
                    <a class="block text-center mt-4 rounded-full bg-brand-blue text-white font-bold py-3" href="{{ $donation->payment_link }}">Open the VISA page</a>
                @endif
            @endif
        </div>
    </div>
</section>
@endsection

@if($donation->status === 'pending')
@push('scripts')
<script>
(function () {
    var url = @json(route('donate.poll', ['token' => $donation->token]));
    function tick() {
        fetch(url, {headers: {'Accept': 'application/json'}}).then(function (res) { return res.json(); }).then(function (body) {
            if (body.status === 'paid' || body.status === 'failed') window.location.reload();
        }).catch(function () {});
    }
    setInterval(tick, 4000);
    var copy = document.getElementById('copyAddress');
    var address = document.getElementById('cryptoAddress');
    if (copy && address) {
        copy.addEventListener('click', function () {
            var text = address.textContent || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            }
            copy.textContent = 'Address copied';
        });
    }
})();
</script>
@endpush
@endif

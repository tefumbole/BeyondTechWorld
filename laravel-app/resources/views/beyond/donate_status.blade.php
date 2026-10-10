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
                    @if($donation->method === 'visa')
                        Open the VISA page and complete the card payment.
                    @else
                        Approve the prompt on your phone. MTN and Orange both use this step.
                    @endif
                </div>
                @if($donation->payment_link)
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
})();
</script>
@endpush
@endif

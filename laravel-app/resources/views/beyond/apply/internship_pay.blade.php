@extends('beyond.layout')

@section('title', 'Pay internship')

@section('content')
<section class="py-10 sm:py-14 bg-slate-50">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <p class="text-xs font-bold uppercase tracking-wider text-brand-gold m-0">Paid internship</p>
            <h1 class="text-2xl font-extrabold text-brand-blue m-0 mt-1">Pay by MoMo</h1>
            <div class="h-0.5 w-12 bg-brand-gold mt-3 mb-4"></div>
            <p class="text-slate-700 m-0">{{ $application->full_name }}</p>
            <p class="text-sm text-slate-500 m-0 mt-1">{{ (int) $application->internship_duration_days }} days · {{ $application->reference_number }}</p>
            <p class="text-3xl font-extrabold text-brand-blue my-3">{{ \App\Support\PaidInternship::money($fee) }}</p>
            @if(session('message'))
                <div class="rounded-lg bg-amber-50 text-amber-900 px-3 py-2 text-sm mb-3">{{ session('message') }}</div>
            @endif
            @if(session('not_permitted'))
                <div class="rounded-lg bg-red-50 text-red-800 px-3 py-2 text-sm mb-3">{{ session('not_permitted') }}</div>
            @endif
            <p class="text-sm text-slate-600" id="waitNote">
                @if($application->internship_fee_reference)
                    A request may already be on the phone. Enter the PIN there to approve it. Stay on this page.
                @else
                    We send the fee to an MTN or Orange Cameroon number. Approve the prompt on that phone.
                @endif
            </p>
            <form method="POST" action="{{ route('apply.internship.pay.start', $application->reference_number) }}" class="mt-4 space-y-3">
                @csrf
                <label class="block text-sm font-bold text-brand-blue" for="momo_phone">MoMo number</label>
                <input id="momo_phone" name="momo_phone" type="tel" required inputmode="tel"
                       value="{{ old('momo_phone', $phone) }}"
                       class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base"
                       placeholder="6XXXXXXXX">
                <button type="submit" class="w-full rounded-full bg-brand-blue text-white font-bold py-3">Send MoMo prompt</button>
            </form>
        </div>
    </div>
</section>
@endsection

@if($application->internship_fee_reference)
@push('scripts')
<script>
(function () {
    var url = @json(route('apply.internship.pay.status', $application->reference_number));
    var note = document.getElementById('waitNote');
    function tick() {
        fetch(url, {headers: {'Accept': 'application/json'}}).then(function (res) { return res.json(); }).then(function (body) {
            if (body.status === 'paid' && body.url) {
                window.location.href = body.url;
                return;
            }
            if (body.status === 'failed' && note) {
                note.textContent = body.message || 'The phone did not approve the payment. You can send the prompt again.';
            }
        }).catch(function () {});
    }
    setInterval(tick, 4000);
})();
</script>
@endpush
@endif

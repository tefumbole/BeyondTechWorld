@extends('beyond.layout')

@section('title', 'Confirm licence receipt')

@section('content')
@include('beyond.antivirus._styles')
<section class="av-page">
    <div class="av-wrap">
        <p class="av-kicker">Receipt · {{ $antivirus->reference }}</p>
        <h1>{{ $antivirus->station }}</h1>
        @if(session('message'))<div class="av-alert ok">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="av-alert bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <div class="av-card">
            <div class="av-qty">{{ $antivirus->quantity }}</div>
            <p>Approved licences for {{ $antivirus->year }}. This confirmation is separate from Finance’s acknowledgement.</p>
            @if($antivirus->receipt_confirmed_at)
                <div class="av-note">
                    Received {{ $antivirus->receipt_quantity }} of {{ $antivirus->quantity }} on {{ $antivirus->receipt_confirmed_at->format('d M Y H:i') }}.
                    Status: {{ $antivirus->receiptLabel() }}.
                    @if($antivirus->receipt_note)<div>{{ $antivirus->receipt_note }}</div>@endif
                </div>
            @elseif(!$verified)
                <h2>Code for {{ $phone }}</h2>
                <form method="POST" action="{{ route('antivirus.receipt.otp', ['token' => $token]) }}">
                    @csrf
                    <input type="hidden" name="send" value="1">
                    <button class="av-btn" type="submit">Send code</button>
                </form>
                <form method="POST" action="{{ route('antivirus.receipt.otp', ['token' => $token]) }}">
                    @csrf
                    <label class="av-field" for="av-otp">Code</label>
                    <input class="av-input" id="av-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" data-autosubmit="1" required>
                    <button class="av-btn secondary" type="submit">Verify</button>
                </form>
                @include('partials.otp_assist', ['otpInputId' => 'av-otp'])
            @else
                <form method="POST" action="{{ route('antivirus.receipt.store', ['token' => $token]) }}">
                    @csrf
                    <label class="av-field" for="received">Quantity received</label>
                    <input class="av-input" id="received" name="quantity" inputmode="numeric" data-wa-phone="off" required value="{{ old('quantity', $antivirus->quantity) }}">
                    <label class="av-field" for="receipt-note">Note, optional</label>
                    <input class="av-input" id="receipt-note" name="note" data-wa-phone="off" value="{{ old('note') }}">
                    <button class="av-btn" type="submit">Confirm receipt</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection

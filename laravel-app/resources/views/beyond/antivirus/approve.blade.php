@extends('beyond.layout')

@section('title', 'Approve antivirus request')

@section('content')
@include('beyond.antivirus._styles')
<section class="av-page">
    <div class="av-wrap">
        <p class="av-kicker">{{ $approver->roleLabel() }}</p>
        <h1>{{ $antivirus->station }}</h1>
        @if(session('message'))<div class="av-alert ok">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="av-alert bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <div class="av-card">
            <div class="av-qty">{{ $antivirus->quantity }}</div>
            <p>Licences for {{ $antivirus->year }} · {{ $antivirus->region }}</p>
            <div class="av-note">
                <div><strong>{{ $antivirus->requester_name }}</strong> · {{ $antivirus->requesterRoleLabel() }}</div>
                <div>Ref {{ $antivirus->reference }}</div>
                <div>Your role on this request: {{ $approver->roleLabel() }}</div>
            </div>
            @if($approver->status === 'signed')
                <p>You signed this request{{ $approver->signed_at ? ' on '.$approver->signed_at->format('d M Y H:i') : '' }}.</p>
            @elseif($approver->status === 'declined' || $antivirus->status === 'declined')
                <p>This request is declined.</p>
            @elseif(!$verified)
                <h2>Code for {{ $phone }}</h2>
                <form method="POST" action="{{ route('antivirus.approve.otp', ['token' => $token]) }}">
                    @csrf
                    <input type="hidden" name="send" value="1">
                    <button class="av-btn" type="submit">Send code</button>
                </form>
                <form method="POST" action="{{ route('antivirus.approve.otp', ['token' => $token]) }}">
                    @csrf
                    <label class="av-field" for="av-otp">Code</label>
                    <input class="av-input" id="av-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" required>
                    <button class="av-btn secondary" type="submit">Verify</button>
                </form>
            @else
                @if($approver->name_source === 'manual' && ! $approver->name_confirmed_at)
                    <div class="av-note">Confirm this is your name: <strong>{{ $approver->name }}</strong></div>
                @endif
                <form method="POST" action="{{ route('antivirus.approve.sign', ['token' => $token]) }}" id="av-sign-form">
                    @csrf
                    @if($approver->name_source === 'manual')
                        <label class="av-choice"><input type="checkbox" name="confirm_name" value="1" required> I confirm my name is {{ $approver->name }}</label>
                    @endif
                    <label class="av-field">Signature</label>
                    <canvas class="av-sign" id="av-pad"></canvas>
                    <input type="hidden" name="signature" value="">
                    <p id="av-sign-error"></p>
                    <div class="av-row">
                        <button class="av-btn secondary" id="av-clear" type="button">Clear</button>
                        <button class="av-btn secondary" id="av-redo" type="button">Redo</button>
                    </div>
                    <button class="av-btn" type="submit">Sign approval</button>
                </form>
                <form method="POST" action="{{ route('antivirus.approve.decline', ['token' => $token]) }}">
                    @csrf
                    <label class="av-field" for="decline-reason">Decline reason</label>
                    <input class="av-input" id="decline-reason" name="reason" data-wa-phone="off" required>
                    <button class="av-btn danger" type="submit">Decline</button>
                </form>
            @endif
        </div>
    </div>
</section>
@include('beyond.antivirus._script')
@endsection

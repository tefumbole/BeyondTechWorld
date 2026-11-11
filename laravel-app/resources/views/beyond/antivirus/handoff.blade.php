@extends('beyond.layout')

@section('title', 'Sign on your phone')

@section('content')
@include('beyond.antivirus._styles')
<section class="av-page">
    <div class="av-wrap">
        <p class="av-kicker">Antivirus {{ $antivirus->year }}</p>
        <h1>{{ $antivirus->station }}</h1>
        @if(session('message'))<div class="av-alert ok">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="av-alert bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <div class="av-card">
            <div class="av-qty">{{ $antivirus->quantity }}</div>
            <p>Licences · {{ $antivirus->region }} · {{ $antivirus->year }}</p>
            @if($antivirus->selfie_path && $antivirus->signatureIsCurrent())
                <div class="av-note">Selfie and signature are saved. Return to the first screen.</div>
            @elseif(!$verified)
                <h2>Code for {{ $phone }}</h2>
                <form method="POST" action="{{ route('antivirus.handoff.otp', ['token' => $token]) }}">
                    @csrf
                    <input type="hidden" name="send" value="1">
                    <button class="av-btn" type="submit">Send code</button>
                </form>
                <form method="POST" action="{{ route('antivirus.handoff.otp', ['token' => $token]) }}">
                    @csrf
                    <label class="av-field" for="av-otp">Code</label>
                    <input class="av-input" id="av-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" required>
                    <button class="av-btn secondary" type="submit">Verify</button>
                </form>
            @else
                <form method="POST" action="{{ route('antivirus.handoff.capture', ['token' => $token]) }}" enctype="multipart/form-data" id="av-capture-form">
                    @csrf
                    <img class="av-selfie" id="av-preview" alt="" hidden>
                    <input id="av-file" type="file" name="selfie" accept="image/*" hidden>
                    <input id="av-fallback" type="file" accept="image/*" capture="user" hidden>
                    <button class="av-btn" id="av-open-camera" type="button">Take a selfie</button>
                    <label class="av-field">Signature</label>
                    <canvas class="av-sign" id="av-pad"></canvas>
                    <input type="hidden" name="signature" value="">
                    <p id="av-sign-error"></p>
                    <div class="av-row">
                        <button class="av-btn secondary" id="av-clear" type="button">Clear</button>
                        <button class="av-btn secondary" id="av-redo" type="button">Redo</button>
                    </div>
                    <button class="av-btn" type="submit">Save selfie and signature</button>
                </form>
                <div class="av-modal" id="av-camera" hidden>
                    <div class="sheet">
                        <video id="av-video" autoplay playsinline muted></video>
                        <button class="av-btn" id="av-shot" type="button">Capture</button>
                        <button class="av-btn secondary" id="av-close-camera" type="button">Close</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@include('beyond.antivirus._script')
@endsection

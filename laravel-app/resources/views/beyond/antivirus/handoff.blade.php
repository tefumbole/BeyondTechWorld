@extends('beyond.layout')

@section('title', ($focus === 'sign' ? 'Signature' : 'Selfie').' | Anti Virus Request')

@section('content')
@include('beyond.antivirus._styles')
<section class="av-page">
    <div class="av-wrap">
        <h1>{{ $focus === 'sign' ? 'Signature' : 'Selfie' }}</h1>
        @if($errors->any())<div class="av-alert bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <div class="av-card">
            @if($done)
                <div class="av-note">{{ session('message', 'Saved. Return to the first screen.') }}</div>
            @elseif($focus === 'sign')
                <form method="POST" action="{{ route('antivirus.handoff.capture', ['token' => $token]) }}" id="av-sign-form">
                    @csrf
                    <input type="hidden" name="focus" value="sign">
                    <div class="av-sign-well">
                        <canvas class="av-sign" id="av-pad"></canvas>
                        <div class="av-sign-hint">Sign in the centre of the box</div>
                    </div>
                    <input type="hidden" name="signature" value="">
                    <p id="av-sign-error" class="av-alert bad" hidden></p>
                    <div class="av-row">
                        <button class="av-btn secondary" id="av-clear" type="button">Clear</button>
                    </div>
                    <button class="av-btn" type="submit">Save signature</button>
                </form>
            @else
                <form method="POST" action="{{ route('antivirus.handoff.capture', ['token' => $token]) }}" enctype="multipart/form-data" id="av-selfie-form">
                    @csrf
                    <input type="hidden" name="focus" value="selfie">
                    <div class="av-frame" id="av-selfie-frame">
                        <img class="av-selfie" id="av-preview" alt="" hidden>
                        <div class="av-frame-hint">Your photo fills this frame</div>
                    </div>
                    <input id="av-file" class="av-file" type="file" name="selfie" accept="image/jpeg,image/png,image/webp" data-skip-image-paste="1">
                    <input id="av-fallback" class="av-file" type="file" accept="image/jpeg,image/png,image/webp" capture="user" data-skip-image-paste="1">
                    <button class="av-btn" id="av-open-camera" type="button">Take a selfie</button>
                </form>
                <div class="av-modal" id="av-camera" hidden>
                    <div class="sheet">
                        <video id="av-video" autoplay playsinline muted></video>
                        <div class="av-row">
                            <button class="av-btn" id="av-shot" type="button">Capture</button>
                            <button class="av-btn secondary" id="av-close-camera" type="button">Close</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@include('beyond.antivirus._script')
@endsection

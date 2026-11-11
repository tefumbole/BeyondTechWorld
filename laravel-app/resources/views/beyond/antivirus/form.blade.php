@extends('beyond.layout')

@section('title', 'Antivirus licences')
@section('meta_description', 'Request antivirus licences for your hospital station.')

@section('content')
@include('beyond.antivirus._styles')
@php
    $steps = [1 => 'Identity', 2 => 'Station', 3 => 'Selfie', 4 => 'Approvers'];
    $a1 = isset($savedApprovers[0]) ? $savedApprovers[0] : [];
    $a2 = isset($savedApprovers[1]) ? $savedApprovers[1] : [];
    $regionValue = old('region', $draft && $draft->region ? $draft->region : '');
@endphp
<section class="av-page">
    <div class="av-wrap">
        <p class="av-kicker">Beyond Enterprise</p>
        <h1>Antivirus licences</h1>
        <p class="av-lead">Request {{ $year }} licences for your station. One or two station officials sign, then the Chief of Administration and Finance approves.</p>

        @if(session('message'))
            <div class="av-alert ok">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="av-alert bad">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="av-steps" aria-label="Progress">
            @foreach($steps as $number => $label)
                <div class="av-step {{ $visual === $number ? 'is-on' : ($visual > $number ? 'is-done' : '') }}">
                    <span>{{ $visual > $number ? '✓' : $number }}</span>
                    {{ $label }}
                </div>
            @endforeach
        </div>

        <div class="av-card">
            @if($panel === 'phone')
                <h2>Verify your identity</h2>
                <p>Enter the Cameroon number that will receive the code.</p>
                <form method="POST" action="{{ route('antivirus.phone') }}">
                    @csrf
                    <label class="av-field" for="av-phone">Phone number</label>
                    <input class="av-input" id="av-phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel" placeholder="+237 6 75 32 17 39" value="{{ old('phone') }}">
                    <button class="av-btn" type="submit">Continue</button>
                </form>
            @elseif($panel === 'name')
                <h2>Confirm your name</h2>
                <p>Code goes to {{ $phone }}.</p>
                @if($suggestedName !== '' && in_array($lookupSource, ['campay', 'pawapay'], true))
                    <div class="av-note">Registered name: <strong>{{ $suggestedName }}</strong></div>
                    <form method="POST" action="{{ route('antivirus.name') }}">
                        @csrf
                        <input type="hidden" name="use_lookup" value="1">
                        <button class="av-btn" type="submit">Yes, this is my name</button>
                    </form>
                    <form method="POST" action="{{ route('antivirus.name') }}" style="margin-top:8px">
                        @csrf
                        <label class="av-field" for="av-manual-name">Or enter your full name</label>
                        <input class="av-input" id="av-manual-name" name="name" data-wa-phone="off" autocomplete="name" placeholder="Full name">
                        <button class="av-btn secondary" type="submit">Use this name</button>
                    </form>
                @else
                    <p>No registered name was found for this number. Enter your full name. The code proves you have the phone.</p>
                    <form method="POST" action="{{ route('antivirus.name') }}">
                        @csrf
                        <label class="av-field" for="av-manual-name">Full name</label>
                        <input class="av-input" id="av-manual-name" name="name" required data-wa-phone="off" autocomplete="name" placeholder="Full name" value="{{ old('name') }}">
                        <button class="av-btn" type="submit">Send code</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('antivirus.restart') }}">@csrf<button class="av-btn secondary" type="submit">Use a different number</button></form>
            @elseif($panel === 'otp')
                <h2>Type the code</h2>
                <p>Sent to {{ $phone }}. It expires in 10 minutes.</p>
                <form method="POST" action="{{ route('antivirus.otp') }}">
                    @csrf
                    <label class="av-field" for="av-otp">Code</label>
                    <input class="av-input" id="av-otp" name="otp" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,8}" placeholder="47 47 82">
                    <button class="av-btn" type="submit">Verify</button>
                </form>
            @elseif($panel === 'role')
                <h2>Your role</h2>
                <p>{{ $draft->requester_name }} · {{ $phone }}</p>
                <form method="POST" action="{{ route('antivirus.role') }}">
                    @csrf
                    @foreach($requesterRoles as $key => $label)
                        <label class="av-choice"><input type="radio" name="role" value="{{ $key }}" {{ old('role') === $key ? 'checked' : '' }} required> {{ $label }}</label>
                    @endforeach
                    <label class="av-field" for="role-other">If you chose Other, describe the role</label>
                    <input class="av-input" id="role-other" name="role_other" data-wa-phone="off" value="{{ old('role_other') }}" placeholder="Role description">
                    <button class="av-btn" type="submit">Continue</button>
                </form>
            @elseif($panel === 'station')
                <h2>Station details</h2>
                <p>Request year <strong>{{ $year }}</strong></p>
                <form method="POST" action="{{ route('antivirus.station') }}">
                    @csrf
                    <label class="av-field" for="region-search">Region</label>
                    <div class="av-region" id="region-box">
                        <input class="av-input" id="region-search" data-wa-phone="off" autocomplete="off" placeholder="Search regions" aria-autocomplete="list" aria-controls="region-list">
                        <input type="hidden" name="region" id="region-value" value="{{ $regionValue }}">
                        <ul id="region-list">
                            @foreach($regions as $region)
                                <li data-region="{{ $region }}" class="{{ $regionValue === $region ? 'is-active' : '' }}">{{ $region }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <label class="av-field" for="av-station">Station or hospital</label>
                    <input class="av-input" id="av-station" name="station" required data-wa-phone="off" value="{{ old('station', $draft->station) }}" placeholder="Hospital or station name">
                    <label class="av-field" for="av-quantity">Total antivirus licences</label>
                    <input class="av-input" id="av-quantity" name="quantity" required inputmode="numeric" data-wa-phone="off" value="{{ old('quantity', $draft->quantity) }}" placeholder="Whole number">
                    <button class="av-btn" type="submit">Continue</button>
                </form>
            @elseif($panel === 'capture')
                <h2>Selfie and signature</h2>
                <p>{{ $draft->station }} · {{ $draft->region }} · {{ $draft->year }}</p>
                <div class="av-qty">{{ $draft->quantity }}</div>
                <p>Licences. A selfie and a signature are required, including IT Technician and Assistant Engineer.</p>
                @if($draft->selfie_path)
                    <p class="av-note">Selfie saved.</p>
                @endif
                <form method="POST" action="{{ route('antivirus.selfie') }}" enctype="multipart/form-data" id="av-selfie-form">
                    @csrf
                    <img class="av-selfie" id="av-preview" alt="" @if(!$draft->selfie_path) hidden @endif>
                    <input id="av-file" type="file" name="selfie" accept="image/*" hidden>
                    <input id="av-fallback" type="file" accept="image/*" capture="user" hidden>
                    <div class="av-row">
                        <button class="av-btn" id="av-open-camera" type="button">Take a selfie</button>
                        <button class="av-btn secondary" type="submit">Save selfie</button>
                    </div>
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
                <form method="POST" action="{{ route('antivirus.signature') }}" id="av-sign-form">
                    @csrf
                    <label class="av-field">Signature</label>
                    <canvas class="av-sign" id="av-pad"></canvas>
                    <input type="hidden" name="signature" value="">
                    <p id="av-sign-error" class="av-alert bad" hidden></p>
                    <div class="av-row">
                        <button class="av-btn secondary" id="av-clear" type="button">Clear</button>
                        <button class="av-btn secondary" id="av-redo" type="button">Redo</button>
                    </div>
                    <button class="av-btn" type="submit">Save signature</button>
                </form>
                <form method="POST" action="{{ route('antivirus.handoff') }}">
                    @csrf
                    <button class="av-btn secondary" type="submit">Continue on another device</button>
                </form>
                @if($qr)
                    <div class="av-note" id="av-poll" data-url="{{ route('antivirus.status') }}">
                        <p>Scan this code. It expires {{ $handoff->expires_at->format('H:i') }}.</p>
                        <div>{!! $qr !!}</div>
                        <p class="av-meta"><span>This screen updates when the phone is done.</span></p>
                    </div>
                @endif
            @elseif($panel === 'approvers')
                <h2>Approvers</h2>
                <p>Choose the role first, then the phone. One approver is required. A second is optional.</p>
                <div class="av-qty">{{ $draft->quantity }}</div>
                <form method="POST" action="{{ route('antivirus.approvers') }}">
                    @csrf
                    @foreach([1 => $a1, 2 => $a2] as $slot => $saved)
                        <div id="approver-{{ $slot }}" @if($slot === 2 && empty($saved['phone']) && old('approver2_phone') === null) hidden @endif>
                            <h3 style="margin:18px 0 0">Approver {{ $slot }}</h3>
                            <label class="av-field" for="approver{{ $slot }}-role">Role</label>
                            <select class="av-select" id="approver{{ $slot }}-role" name="approver{{ $slot }}_role">
                                <option value="">Choose a role</option>
                                @foreach($approverRoles as $key => $label)
                                    <option value="{{ $key }}" {{ old('approver'.$slot.'_role', isset($saved['role']) ? $saved['role'] : '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="av-field" for="approver{{ $slot }}-other">If Other, describe the role</label>
                            <input class="av-input" id="approver{{ $slot }}-other" name="approver{{ $slot }}_role_other" data-wa-phone="off" value="{{ old('approver'.$slot.'_role_other', isset($saved['role_other']) ? $saved['role_other'] : '') }}">
                            <label class="av-field" for="approver{{ $slot }}-phone">Phone number</label>
                            <input class="av-input" id="approver{{ $slot }}-phone" name="approver{{ $slot }}_phone" type="tel" inputmode="tel" value="{{ old('approver'.$slot.'_phone', isset($saved['phone']) ? $saved['phone'] : '') }}" placeholder="+237 6 75 32 17 39">
                            <button class="av-btn secondary" type="button" data-lookup="{{ $slot }}" data-url="{{ route('antivirus.lookup') }}">Find registered name</button>
                            <p id="approver{{ $slot }}-found" class="av-note"></p>
                            <label class="av-field" for="approver{{ $slot }}-name">Full name if lookup is empty or wrong</label>
                            <input class="av-input" id="approver{{ $slot }}-name" name="approver{{ $slot }}_manual_name" data-wa-phone="off" value="{{ old('approver'.$slot.'_manual_name', (isset($saved['name_source']) && $saved['name_source'] === 'manual') ? $saved['name'] : '') }}">
                        </div>
                    @endforeach
                    <button class="av-btn secondary" id="add-approver" type="button" @if(!empty($a2['phone'])) hidden @endif>+ Add approver</button>
                    <button class="av-btn" type="submit">Review request</button>
                </form>
            @elseif($panel === 'review')
                <h2>Review and send</h2>
                <div class="av-qty">{{ $draft->quantity }}</div>
                <p>Licences for {{ $draft->year }}</p>
                <div class="av-note">
                    <div><strong>{{ $draft->requester_name }}</strong> · {{ $draft->requesterRoleLabel() }}</div>
                    <div>{{ $draft->station }}, {{ $draft->region }}</div>
                    @foreach($savedApprovers as $person)
                        <div>{{ $person['name'] }} · {{ \App\Support\AntivirusCatalog::roleLabel('approver', $person['role'], isset($person['role_other']) ? $person['role_other'] : '') }}</div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('antivirus.submit') }}">
                    @csrf
                    <button class="av-btn" type="submit">Submit request</button>
                </form>
                <form method="POST" action="{{ route('antivirus.approvers.edit') }}">
                    @csrf
                    <button class="av-btn secondary" type="submit">Edit approvers</button>
                </form>
            @else
                <h2>Request submitted</h2>
                <div class="av-ref">{{ $draft->reference }}</div>
                <p>{{ $draft->decisionLabel() }}</p>
                <div class="av-note">
                    <div class="av-qty" style="font-size:42px">{{ $draft->quantity }}</div>
                    <div>{{ $draft->station }} · {{ $draft->year }}</div>
                    <div>A WhatsApp message names the station approvers. After they sign, the request goes to the Chief of Administration and Finance.</div>
                </div>
                <form method="POST" action="{{ route('antivirus.restart') }}">@csrf<button class="av-btn secondary" type="submit">Start another request</button></form>
            @endif
        </div>
    </div>
</section>
@include('beyond.antivirus._script')
@endsection

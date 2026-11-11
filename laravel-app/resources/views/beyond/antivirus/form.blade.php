@extends('beyond.layout')

@section('title', 'CBC Health Services, Anti Virus Request form '.$year)
@section('meta_description', 'CBC Health Services anti virus request form for '.$year.'.')

@section('content')
@include('beyond.antivirus._styles')
@php
    $a1 = isset($savedApprovers[0]) ? $savedApprovers[0] : [];
    $a2 = isset($savedApprovers[1]) ? $savedApprovers[1] : [];
    $regionValue = old('region', $draft && $draft->region ? $draft->region : '');
    $lookupPhone = (string) session('antivirus_lookup_phone', '');
    $lookupName = (string) session('antivirus_lookup_name', '');
    $phoneShown = old('phone', $lookupPhone !== '' ? \App\Support\WhatsAppPhone::display($lookupPhone) : '');
    $nameShown = old('name', $lookupName);
    $roleOld = (string) old('role', '');
    $roleShown = ($roleOld !== '' && isset($requesterRoles[$roleOld])) ? $requesterRoles[$roleOld] : '';
@endphp
<section class="av-page">
    <div class="av-wrap">
        <p class="av-kicker">CBC Health Services</p>
        <h1>Anti Virus Request form {{ $year }}</h1>
        <div class="av-progress" aria-label="{{ $percent }} percent complete">
            <div class="av-progress-bar"><span style="width: {{ (int) $percent }}%"></span></div>
            <p>{{ (int) $percent }}% complete</p>
        </div>

        @if(session('message'))
            <div class="av-alert ok">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="av-alert bad">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="av-card">
            @if($panel === 'phone')
                <h2>Your phone number</h2>
                <p>The name appears when the number is complete. You can change it. Continue sends a code.</p>
                <form method="POST" action="{{ route('antivirus.phone') }}" id="av-identity">
                    @csrf
                    <label class="av-field" for="av-phone">Phone number</label>
                    <input class="av-input" id="av-phone" name="phone" type="tel" required inputmode="tel" autocomplete="tel" placeholder="+237 6 75 32 17 39" value="{{ $phoneShown }}" data-lookup-url="{{ route('antivirus.lookup') }}">
                    <div id="av-name-wrap" class="{{ ($nameShown !== '' || $lookupPhone !== '') ? '' : 'is-hidden' }}">
                        <label class="av-field" for="av-name">Name</label>
                        <input class="av-input" id="av-name" name="name" data-wa-phone="off" autocomplete="name" placeholder="Full name" value="{{ $nameShown }}" data-filled="{{ $nameShown !== '' ? '1' : '0' }}">
                    </div>
                    <button class="av-btn" type="submit">Continue</button>
                </form>
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
                <p>{{ $draft->requester_name }}</p>
                <form method="POST" action="{{ route('antivirus.role') }}">
                    @csrf
                    <label class="av-field" for="role-search">Role</label>
                    <div class="av-combo">
                        <input class="av-input" id="role-search" type="search" data-wa-phone="off" autocomplete="off" placeholder="Search your role" value="{{ $roleShown }}">
                        <input type="hidden" name="role" id="role-value" value="{{ $roleOld }}">
                        <div id="role-menu" class="av-menu" hidden></div>
                    </div>
                    <div id="role-other-wrap" class="{{ $roleOld === 'other' ? '' : 'is-hidden' }}">
                        <label class="av-field" for="role-other">Describe the role</label>
                        <input class="av-input" id="role-other" name="role_other" data-wa-phone="off" value="{{ old('role_other') }}" placeholder="Role description">
                    </div>
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
                        <ul id="region-list" hidden>
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
                <h2>Selfie</h2>
                <form method="POST" action="{{ route('antivirus.selfie') }}" enctype="multipart/form-data" id="av-selfie-form">
                    @csrf
                    <img class="av-selfie" id="av-preview" alt="" hidden>
                    @if($draft->selfie_path)<p class="av-note">Selfie saved.</p>@endif
                    <input id="av-file" class="av-file" type="file" name="selfie" accept="image/jpeg,image/png,image/webp" data-skip-image-paste="1">
                    <input id="av-fallback" class="av-file" type="file" accept="image/jpeg,image/png,image/webp" capture="user" data-skip-image-paste="1">
                    <div class="av-row">
                        <button class="av-btn" id="av-open-camera" type="button">Take a selfie</button>
                        <button class="av-btn secondary" type="button" data-qr="selfie">Continue on another device</button>
                    </div>
                    <div class="av-qr-slot {{ ($qr && $handoffFocus === 'selfie') ? 'is-open' : '' }}" data-qr-slot="selfie">
                        @if($qr)<div class="av-qr">{!! $qr !!}</div>@endif
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
                        <button class="av-btn secondary" type="button" data-qr="sign">Continue on another device</button>
                    </div>
                    <div class="av-qr-slot {{ ($qr && $handoffFocus === 'sign') ? 'is-open' : '' }}" data-qr-slot="sign">
                        @if($qr)<div class="av-qr">{!! $qr !!}</div>@endif
                    </div>
                    <button class="av-btn" type="submit">Continue</button>
                </form>
                <form id="av-handoff-form" method="POST" action="{{ route('antivirus.handoff') }}" hidden>
                    @csrf
                    <input type="hidden" name="focus" id="av-handoff-focus" value="selfie">
                </form>
                <div id="av-poll" data-url="{{ route('antivirus.status') }}" data-selfie="{{ $draft->selfie_path ? '1' : '0' }}" data-signature="{{ ($draft->signature_path && $draft->signatureIsCurrent()) ? '1' : '0' }}" hidden></div>
            @elseif($panel === 'approvers')
                <h2>Approvers</h2>
                <p>Add a phone number. The name appears for you to edit, then choose the role.</p>
                <form method="POST" action="{{ route('antivirus.approvers') }}" id="av-approver-form" data-lookup-url="{{ route('antivirus.lookup') }}">
                    @csrf
                    <div id="approver-list">
                        @foreach([1 => $a1, 2 => $a2] as $slot => $saved)
                            @php
                                $savedPhone = old('approver'.$slot.'_phone', isset($saved['phone']) ? $saved['phone'] : '');
                                $savedRole = (string) old('approver'.$slot.'_role', isset($saved['role']) ? $saved['role'] : '');
                                $savedRoleLabel = ($savedRole !== '' && isset($approverRoles[$savedRole])) ? $approverRoles[$savedRole] : '';
                                $savedName = old('approver'.$slot.'_manual_name', isset($saved['name']) ? $saved['name'] : '');
                                $showCard = $savedPhone !== '' || $savedRole !== '' || $savedName !== '';
                            @endphp
                            @if($showCard)
                                <div class="av-person" data-slot="{{ $slot }}">
                                    <label class="av-field">Phone number</label>
                                    <input class="av-input av-approver-phone" name="approver{{ $slot }}_phone" type="tel" inputmode="tel" value="{{ $savedPhone }}" placeholder="+250 794 006 160">
                                    <label class="av-field">Name</label>
                                    <input class="av-input" name="approver{{ $slot }}_manual_name" data-wa-phone="off" value="{{ $savedName }}" placeholder="Full name">
                                    <label class="av-field">Role</label>
                                    <div class="av-combo">
                                        <input class="av-input av-role-search" type="search" data-wa-phone="off" autocomplete="off" placeholder="Search the role" value="{{ $savedRoleLabel }}">
                                        <input type="hidden" name="approver{{ $slot }}_role" value="{{ $savedRole }}">
                                        <div class="av-menu" hidden></div>
                                    </div>
                                    <div class="{{ $savedRole === 'other' ? '' : 'is-hidden' }} av-role-other">
                                        <label class="av-field">Describe the role</label>
                                        <input class="av-input" name="approver{{ $slot }}_role_other" data-wa-phone="off" value="{{ old('approver'.$slot.'_role_other', isset($saved['role_other']) ? $saved['role_other'] : '') }}">
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <button class="av-plus" id="add-approver" type="button" aria-label="Add approver">+</button>
                    <button class="av-btn" type="submit">Review request</button>
                </form>
                <script type="application/json" id="av-approver-roles">@json($approverRoles)</script>
            @elseif($panel === 'review')
                <h2>Review and send</h2>
                <div class="av-note">
                    <div class="av-request-title">Anti Virus Request {{ $draft->year }}</div>
                    <div class="av-fact"><span>Station</span><strong>{{ $draft->station }}</strong></div>
                    <div class="av-fact"><span>Number</span><strong>{{ $draft->quantity }}</strong></div>
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
                    <div class="av-request-title">Anti Virus Request {{ $draft->year }}</div>
                    <div class="av-fact"><span>Station</span><strong>{{ $draft->station }}</strong></div>
                    <div class="av-fact"><span>Number</span><strong>{{ $draft->quantity }}</strong></div>
                    <p class="av-after">A WhatsApp message names the station approvers. After they sign, the request goes to the Chief of Administration and Finance.</p>
                </div>
                <form method="POST" action="{{ route('antivirus.restart') }}">@csrf<button class="av-btn secondary" type="submit">Start another request</button></form>
            @endif
        </div>
    </div>
</section>
<script type="application/json" id="av-requester-roles">@json($requesterRoles)</script>
@include('beyond.antivirus._script')
@endsection

@extends('gbhs.public.layout')
@section('content')
<style>
    .join { max-width: 760px; margin: 0 auto; }
    .stage-nav { display: flex; justify-content: space-between; gap: 8px; margin: 8px 0 18px; }
    .stage { flex: 1; text-align: center; color: #5c6b62; font-size: .92rem; font-weight: 700; }
    .stage span { display: grid; place-items: center; width: 36px; height: 36px; margin: 0 auto 6px; border-radius: 50%; background: #fff; border: 1px solid #e4d3a2; color: #5c6b62; }
    .stage.on span, .stage.done span { background: var(--forest); color: var(--gold); border-color: var(--forest); }
    .stage.on { color: var(--forest); }
    .join-card { background: #fff; border: 1px solid #e4d3a2; border-radius: 16px; padding: 22px 22px 26px; box-shadow: 0 10px 30px rgba(18, 53, 31, .06); }
    .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .fields .wide { grid-column: 1 / -1; }
    .batch-box { position: relative; }
    .batch-box ul:empty { display: none; }
    .batch-box ul, .fill-menu { list-style: none; margin: 6px 0 0; padding: 6px; max-height: 240px; overflow: auto; background: #fff; border: 1px solid #e4d3a2; border-radius: 12px; position: absolute; z-index: 6; left: 0; right: 0; box-shadow: 0 14px 32px rgba(18, 53, 31, .14); }
    .batch-box li, .fill-menu li { padding: 10px 12px; cursor: pointer; border-radius: 8px; color: #0c3a22; }
    .batch-box li:hover, .batch-box li.on, .fill-menu li:hover, .fill-menu li.on { background: #0c3a22; color: #f4d27a; }
    .fill-select { position: relative; }
    .fill-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; border: 1px solid #d7c59a; border-radius: 8px; background: linear-gradient(#fff, #fffdf6); color: #0c3a22; font: inherit; font-size: 1.1rem; text-align: left; cursor: pointer; }
    .fill-btn.is-empty { color: #8d8474; }
    .fill-caret { color: #b0893e; font-size: .9rem; }
    .home-pick { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 8px; }
    .home-card { display: block; text-decoration: none; background: #fffdf8; border: 1px solid #e4d3a2; border-radius: 16px; padding: 26px 16px; color: #0c3a22; }
    .home-card strong { display: block; font-size: 1.4rem; margin-bottom: 6px; }
    .home-card span { color: #5c6b62; }
    .home-card:hover { background: #0c3a22; border-color: #0c3a22; }
    .home-card:hover strong, .home-card:hover span { color: #f4d27a; }
    .photo-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .photo-row button { margin: 8px 0 0; }
    .photo-preview { width: 120px; height: 80px; object-fit: cover; border-radius: 12px; border: 1px solid #e4d3a2; }
    .check { display: flex; gap: 8px; align-items: flex-start; font-weight: 600; }
    .check input { width: auto; margin-top: 4px; }
    .choice { display: flex; gap: 8px; align-items: center; border: 1px solid #e4d3a2; border-radius: 12px; padding: 10px 12px; background: #fffdf8; }
    .choice input { width: auto; }
    .laws { background: #0c3a22; border-radius: 16px; padding: 14px; margin: 12px 0 18px; }
    .law { display: flex; gap: 12px; background: #12351f; border: 1px solid rgba(232, 193, 90, .45); border-radius: 12px; padding: 14px; margin: 0 0 10px; color: #f7f3e8; }
    .law:last-child { margin-bottom: 0; }
    .law-n { flex: 0 0 36px; height: 36px; border-radius: 10px; background: #0c3a22; border: 1px solid var(--gold); color: var(--gold); display: grid; place-items: center; font-weight: 700; }
    .law h3 { margin: 0 0 4px; color: var(--gold); font-size: 1.05rem; }
    .law p { margin: 0; line-height: 1.45; }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    @media (max-width: 700px) { .fields, .stage-nav, .home-pick { grid-template-columns: 1fr; } .stage-nav { display: grid; grid-template-columns: repeat(4, 1fr); } }
</style>
@php
    $batchRows = [];
    foreach ($batches as $batch) {
        $batchRows[] = ['id' => $batch->id, 'name' => $batch->name];
    }
    $stageOrder = ['details' => 'Details', 'id' => 'ID', 'portrait' => 'Portrait', 'sign' => 'Sign'];
    $stageIndex = array_search($step, array_keys($stageOrder), true);
    $marital = ['Married', 'Single', 'Divorced', 'Widower', 'Widow', 'Other'];
    $employment = ['Employed', 'Unemployed', 'Freelancer'];
    $home = isset($home) && $home === 'diaspora' ? 'diaspora' : 'cameroon';
    $pickedCountry = $application && $application->country ? $application->country : '';
    if ($home === 'cameroon') {
        $pickedCountry = 'Cameroon';
    } elseif ($pickedCountry === 'Cameroon') {
        $pickedCountry = '';
    }
    $pickedRegion = $application ? (string) $application->region : '';
    $divisionLabels = isset($divisionLabels) ? $divisionLabels : [];
@endphp
<div class="join">
    <div class="stage-nav">
        @foreach($stageOrder as $key => $label)
            @php $index = array_search($key, array_keys($stageOrder), true); @endphp
            <div class="stage {{ $step === $key ? 'on' : ($stageIndex !== false && $index < $stageIndex ? 'done' : '') }}">
                <span>{{ $index + 1 }}</span>{{ $label }}
            </div>
        @endforeach
    </div>
@if($step === 'intro')
    <div class="join-card" style="text-align:center">
        <p class="muted" style="letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:var(--gold-deep)">GBHS Kumbo</p>
        <h1>Become a Member</h1>
        <p>Choose where you live. Cameroon uses regions. Diaspora uses a state, province, or region for the country you select.</p>
        <div class="home-pick">
            <a class="home-card" href="{{ route('gbhs.membership', ['home' => 'cameroon']) }}"><strong>Cameroon</strong><span>Pick your region</span></a>
            <a class="home-card" href="{{ route('gbhs.membership', ['home' => 'diaspora']) }}"><strong>Diaspora</strong><span>State or region for your country</span></a>
        </div>
    </div>
@elseif($step === 'details')
    <form class="join-card" method="post" action="{{ route('gbhs.register.profile') }}">
        @csrf
        <h1>Your details</h1>
        <p class="muted">{{ $home === 'diaspora' ? 'You are registering from the diaspora. The state or region list follows the country you select.' : 'You are registering from Cameroon. Choose your region.' }} Email and employer are optional.</p>
        <div class="fields">
            <div class="wide">
                <label>Phone number</label>
                @if($application && $application->phone)
                    <input value="{{ $application->phone }}" readonly>
                @else
                    <div class="phone-row">
                        @include('gbhs.public.partials.country_code')
                        <input name="phone" id="joinPhone" required placeholder="675321739" inputmode="tel" autocomplete="tel">
                    </div>
                @endif
            </div>
            <div class="wide">
                <label>Full name</label>
                <input name="full_name" id="joinName" value="{{ $application->full_name ?? '' }}" required>
                <p class="muted" id="joinHint">Type the number. We fill the Mobile Money name when we find it. You can edit it.</p>
            </div>
            <div class="wide batch-box">
                <label>Batch</label>
                <input type="search" id="batchSearch" placeholder="Search the year you finished or attended" autocomplete="off" required>
                <input type="hidden" name="batch_id" id="batchId" value="{{ $application->batch_id ?? '' }}">
                <ul id="batchList"></ul>
            </div>
            <div><label>Date of birth</label><input type="date" name="date_of_birth" value="{{ $application->date_of_birth ?? '' }}"></div>
            <div>
                <label>Gender</label>
                <div class="fill-select" data-fill>
                    <button type="button" class="fill-btn {{ ($application->gender ?? '') === '' ? 'is-empty' : '' }}"><span data-fill-label>{{ ($application->gender ?? '') !== '' ? $application->gender : 'Select' }}</span><span class="fill-caret">▾</span></button>
                    <input type="hidden" name="gender" value="{{ $application->gender ?? '' }}">
                    <ul class="fill-menu" hidden>
                        @foreach(['Female', 'Male'] as $gender)
                            <li data-value="{{ $gender }}" class="{{ ($application->gender ?? '') === $gender ? 'on' : '' }}">{{ $gender }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div>
                <label>Marital status</label>
                <div class="fill-select" data-fill data-required="1">
                    <button type="button" class="fill-btn {{ ($application->marital_status ?? '') === '' ? 'is-empty' : '' }}"><span data-fill-label>{{ ($application->marital_status ?? '') !== '' ? $application->marital_status : 'Select' }}</span><span class="fill-caret">▾</span></button>
                    <input type="hidden" name="marital_status" value="{{ $application->marital_status ?? '' }}">
                    <ul class="fill-menu" hidden>
                        @foreach($marital as $item)
                            <li data-value="{{ $item }}" class="{{ ($application->marital_status ?? '') === $item ? 'on' : '' }}">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div><label>Years attended</label><input name="years_attended" value="{{ $application->years_attended ?? '' }}" placeholder="Optional"></div>
            <div><label>Profession</label><input name="profession" value="{{ $application->profession ?? '' }}" placeholder="Optional"></div>
            <div>
                <label>Employment status</label>
                <div class="fill-select" data-fill data-required="1">
                    <button type="button" class="fill-btn {{ ($application->employment_status ?? '') === '' ? 'is-empty' : '' }}"><span data-fill-label>{{ ($application->employment_status ?? '') !== '' ? $application->employment_status : 'Select' }}</span><span class="fill-caret">▾</span></button>
                    <input type="hidden" name="employment_status" value="{{ $application->employment_status ?? '' }}">
                    <ul class="fill-menu" hidden>
                        @foreach($employment as $item)
                            <li data-value="{{ $item }}" class="{{ ($application->employment_status ?? '') === $item ? 'on' : '' }}">{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div><label>Employer <span class="muted">optional</span></label><input name="employer" value="{{ $application->employer ?? '' }}"></div>
            <div class="wide"><label>Email <span class="muted">optional</span></label><input type="email" name="email" value="{{ $application->email ?? '' }}"></div>
            @if($home === 'diaspora')
            <div class="batch-box">
                <label>Country</label>
                <input type="search" id="countrySearch" placeholder="Search country" autocomplete="off" required value="{{ $pickedCountry }}">
                <input type="hidden" name="country" id="countryValue" value="{{ $pickedCountry }}">
                <ul id="countryList"></ul>
            </div>
            @else
            <div>
                <label>Country</label>
                <input value="Cameroon" readonly>
                <input type="hidden" name="country" id="countryValue" value="Cameroon">
            </div>
            @endif
            <div class="batch-box" id="regionBox">
                <label id="regionLabel">{{ $home === 'cameroon' ? 'Region' : ($pickedCountry ? \App\Support\CountryRegions::label($pickedCountry) : 'State or region') }}</label>
                <input type="search" id="regionSearch" placeholder="Search {{ strtolower($home === 'cameroon' ? 'region' : ($pickedCountry ? \App\Support\CountryRegions::label($pickedCountry) : 'state or region')) }}" autocomplete="off" required value="{{ $pickedRegion }}">
                <input type="hidden" name="region" id="regionValue" value="{{ $pickedRegion }}">
                <ul id="regionList"></ul>
            </div>
            <div id="regionText" hidden>
                <label id="regionFreeLabel">State or region</label>
                <input id="regionFree" placeholder="Type your state or region" value="{{ $pickedRegion }}">
            </div>
            <div><label>City</label><input name="city" value="{{ $application->city ?? '' }}" placeholder="Optional"></div>
        </div>
        <button type="submit">Continue</button>
    </form>
    <script>
        (function () {
            var rows = @json($batchRows);
            var countries = @json(array_values($countries));
            var regions = @json($regions);
            var divisionLabels = @json($divisionLabels);
            document.querySelectorAll('[data-fill]').forEach(function (box) {
                var button = box.querySelector('.fill-btn');
                var hidden = box.querySelector('input[type="hidden"]');
                var menu = box.querySelector('.fill-menu');
                var label = box.querySelector('[data-fill-label]');
                button.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var open = menu.hidden;
                    document.querySelectorAll('.fill-menu').forEach(function (other) { other.hidden = true; });
                    menu.hidden = !open;
                });
                menu.querySelectorAll('li').forEach(function (item) {
                    item.addEventListener('click', function () {
                        hidden.value = item.getAttribute('data-value');
                        label.textContent = item.textContent;
                        button.classList.remove('is-empty');
                        menu.querySelectorAll('li').forEach(function (other) { other.classList.remove('on'); });
                        item.classList.add('on');
                        menu.hidden = true;
                    });
                });
            });
            document.addEventListener('click', function () {
                document.querySelectorAll('.fill-menu').forEach(function (menu) { menu.hidden = true; });
            });
            function picker(search, hidden, list, choices, onPick) {
                function paint() {
                    var q = (search.value || '').toLowerCase().trim();
                    var shown = !q ? choices : choices.filter(function (name) { return String(name).toLowerCase().indexOf(q) !== -1; });
                    list.innerHTML = '';
                    shown.slice(0, 40).forEach(function (name) {
                        var li = document.createElement('li');
                        if (name === hidden.value) li.className = 'on';
                        li.textContent = name;
                        li.addEventListener('click', function () {
                            hidden.value = name;
                            search.value = name;
                            list.innerHTML = '';
                            if (onPick) onPick(name);
                        });
                        list.appendChild(li);
                    });
                }
                search.addEventListener('input', function () { hidden.value = ''; paint(); });
                search.addEventListener('focus', paint);
                document.addEventListener('click', function (e) { if (!search.parentNode.contains(e.target)) list.innerHTML = ''; });
                return paint;
            }
            var batchSearch = document.getElementById('batchSearch');
            var batchId = document.getElementById('batchId');
            var batchList = document.getElementById('batchList');
            var current = rows.filter(function (r) { return String(r.id) === String(batchId.value); })[0];
            if (current) batchSearch.value = current.name;
            function paintBatch() {
                var q = (batchSearch.value || '').toLowerCase().trim();
                var shown = !q ? rows : rows.filter(function (r) { return String(r.name).toLowerCase().indexOf(q) !== -1; });
                batchList.innerHTML = '';
                shown.forEach(function (r) {
                    var li = document.createElement('li');
                    if (String(r.id) === String(batchId.value)) li.className = 'on';
                    li.textContent = r.name;
                    li.addEventListener('click', function () {
                        batchId.value = r.id;
                        batchSearch.value = r.name;
                        batchList.innerHTML = '';
                    });
                    batchList.appendChild(li);
                });
            }
            batchSearch.addEventListener('input', function () { batchId.value = ''; paintBatch(); });
            batchSearch.addEventListener('focus', paintBatch);
            document.addEventListener('click', function (e) { if (!batchSearch.parentNode.contains(e.target)) batchList.innerHTML = ''; });
            var countrySearch = document.getElementById('countrySearch');
            var countryValue = document.getElementById('countryValue');
            var regionSearch = document.getElementById('regionSearch');
            var regionValue = document.getElementById('regionValue');
            var regionBox = document.getElementById('regionBox');
            var regionText = document.getElementById('regionText');
            var regionFree = document.getElementById('regionFree');
            var regionChoices = (regions[countryValue.value] || []).slice();
            var regionLabel = document.getElementById('regionLabel');
            var regionFreeLabel = document.getElementById('regionFreeLabel');
            if (countrySearch && countryValue.value) countrySearch.value = countryValue.value;
            function divisionWord(name) {
                return divisionLabels[name] || (name ? 'State or region' : 'State or region');
            }
            function applyCountry(name, keep) {
                var word = divisionWord(name);
                var lower = word.toLowerCase();
                regionLabel.textContent = word;
                regionFreeLabel.textContent = word;
                regionSearch.placeholder = 'Search ' + lower;
                regionFree.placeholder = 'Type your ' + lower;
                var list = regions[name] || [];
                regionChoices.splice(0, regionChoices.length);
                list.forEach(function (item) { regionChoices.push(item); });
                if (!list.length) {
                    regionBox.hidden = true;
                    regionSearch.required = false;
                    regionText.hidden = false;
                    regionFree.required = true;
                    regionFree.name = 'region';
                    regionValue.disabled = true;
                    return;
                }
                regionBox.hidden = false;
                regionSearch.required = true;
                regionText.hidden = true;
                regionFree.required = false;
                regionFree.name = '';
                regionValue.disabled = false;
                if (keep && list.indexOf(regionValue.value) !== -1) {
                    regionSearch.value = regionValue.value;
                } else if (!keep) {
                    regionValue.value = '';
                    regionSearch.value = '';
                } else {
                    regionValue.value = '';
                    regionSearch.value = '';
                }
            }
            if (countrySearch) {
                picker(countrySearch, countryValue, document.getElementById('countryList'), countries, function (name) { applyCountry(name, false); });
            }
            picker(regionSearch, regionValue, document.getElementById('regionList'), regionChoices, null);
            applyCountry(countryValue.value || 'Cameroon', true);
            var phone = document.getElementById('joinPhone');
            if (phone && window.gbhsLookupName) {
                gbhsLookupName(phone, document.querySelector('form [data-cc-value]'), document.getElementById('joinName'), document.getElementById('joinHint'));
            }
            document.querySelector('form').addEventListener('submit', function (e) {
                if (!batchId.value) { e.preventDefault(); batchSearch.focus(); paintBatch(); }
                if (countrySearch && !countryValue.value) { e.preventDefault(); countrySearch.focus(); }
                document.querySelectorAll('[data-fill][data-required]').forEach(function (box) {
                    if (!box.querySelector('input').value) {
                        e.preventDefault();
                        box.querySelector('.fill-menu').hidden = false;
                    }
                });
                if (!regionValue.disabled && !regionValue.value && regionChoices.indexOf(regionSearch.value) !== -1) {
                    regionValue.value = regionSearch.value;
                }
            });
        })();
    </script>
@elseif($step === 'id')
    <form class="join-card" method="post" action="{{ route('gbhs.register.identification') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="action" id="idAction" value="save">
        <h1>Identification</h1>
        <p class="muted">A national ID card is for Cameroon. A passport works for every country, including Cameroon. Scan or upload a photo and we fill the name, number, and date of issue. The image is deleted. You can skip this and add it later.</p>
        <div class="fields">
            <label class="choice"><input type="radio" name="id_type" value="national_id" {{ ($application->id_type ?: ($application->country === 'Cameroon' ? 'national_id' : 'passport')) === 'national_id' ? 'checked' : '' }}> National ID card</label>
            <label class="choice"><input type="radio" name="id_type" value="passport" {{ ($application->id_type ?: ($application->country === 'Cameroon' ? 'national_id' : 'passport')) === 'passport' ? 'checked' : '' }}> Passport</label>
        </div>
        <div class="photo-row">
            <button type="button" class="btn" id="idCam">Scan</button>
            <button type="button" class="btn gold" id="idFile">Upload</button>
            <img class="photo-preview" id="idPreview" alt="" hidden>
        </div>
        <input type="file" name="document" id="idInput" accept="image/*" hidden>
        <div class="fields">
            <div class="wide"><label>Name on the document</label><input name="id_holder_name" value="{{ $application->id_holder_name }}"></div>
            <div><label>ID or passport number</label><input name="id_number" value="{{ $application->id_number }}"></div>
            <div><label>Date of issue</label><input type="date" name="id_issued_on" value="{{ $application->id_issued_on }}"></div>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('gbhs.membership', ['step' => 'details']) }}">Back</a>
            <button type="submit">Continue</button>
            <button type="submit" class="btn" onclick="document.getElementById('idAction').value='skip'">Skip for now</button>
        </div>
    </form>
    <script>
        (function () {
            var input = document.getElementById('idInput');
            var preview = document.getElementById('idPreview');
            function arm(camera) {
                gbhsPickPhoto(input, camera, preview);
            }
            document.getElementById('idCam').addEventListener('click', function () { arm(true); });
            document.getElementById('idFile').addEventListener('click', function () { arm(false); });
            input.addEventListener('change', function () {
                if (!input.files || !input.files[0]) return;
                document.getElementById('idAction').value = 'read';
                input.form.submit();
            });
        })();
    </script>
@elseif($step === 'portrait')
    <form class="join-card" method="post" action="{{ route('gbhs.register.portrait') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="action" id="photoAction" value="save">
        <h1>Portrait</h1>
        <p class="muted">Take a selfie or attach a photo. This can be added later.</p>
        <div class="photo-row">
            <button type="button" class="btn" id="photoCam">Take a selfie</button>
            <button type="button" class="btn gold" id="photoFile">Attach a photo</button>
            <img class="photo-preview" id="photoPreview" alt="" hidden @if($application->photo) src="{{ url('public/'.$application->photo) }}" hidden="false" @endif>
        </div>
        <input type="file" name="photo" id="photoInput" accept="image/*" hidden>
        <div class="actions">
            <a class="btn" href="{{ route('gbhs.membership', ['step' => 'id']) }}">Back</a>
            <button type="submit">Continue</button>
            <button type="submit" class="btn" onclick="document.getElementById('photoAction').value='skip'">Skip for now</button>
        </div>
    </form>
    <script>
        (function () {
            var input = document.getElementById('photoInput');
            var preview = document.getElementById('photoPreview');
            if (preview.getAttribute('src')) preview.hidden = false;
            document.getElementById('photoCam').addEventListener('click', function () { gbhsPickPhoto(input, true, preview); });
            document.getElementById('photoFile').addEventListener('click', function () { gbhsPickPhoto(input, false, preview); });
        })();
    </script>
@elseif($step === 'sign')
    <form class="join-card" method="post" action="{{ route('gbhs.register.sign') }}">
        @csrf
        <h1>Bylaws and signature</h1>
        <p class="muted">Read the published bylaws, then sign. {{ $bylaw ? 'Version '.$bylaw->version.'.' : '' }}</p>
        <div class="laws">
            @foreach($articles as $article)
                <article class="law">
                    <div class="law-n">{{ $article['n'] }}</div>
                    <div>
                        <h3>{{ $article['title'] }}</h3>
                        <p>{{ $article['body'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
        @if($bylaw && $bylaw->file)
            <p><a href="{{ route('gbhs.bylaw', $bylaw->id) }}" target="_blank">Open the bylaws PDF</a></p>
            <label class="check"><input type="checkbox" name="agree_bylaws" value="1" required> I have read these bylaws</label>
        @else
            <p>The association has not published the bylaws yet, so this application cannot be submitted.</p>
        @endif
        <label class="check"><input type="checkbox" name="consent_processing" value="1" required> I agree that the association may keep these details to review the application</label>
        <label class="check"><input type="checkbox" name="consent_announcements" value="1"> Send me WhatsApp updates about this application</label>
        <canvas id="pad" width="640" height="180" style="border:1px dashed var(--forest);border-radius:12px;background:#fff;width:100%;touch-action:none"></canvas>
        <input type="hidden" name="signature" id="signature">
        <div class="actions">
            <a class="btn" href="{{ route('gbhs.membership', ['step' => 'portrait']) }}">Back</a>
            <button type="button" id="clear">Clear</button>
            <button type="submit" {{ $bylaw && $bylaw->file ? '' : 'disabled' }}>Submit application</button>
        </div>
    </form>
    <script>
        var canvas = document.getElementById('pad');
        var ctx = canvas.getContext('2d');
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        var drawing = false;
        function pos(e) {
            var r = canvas.getBoundingClientRect();
            var p = e.touches ? e.touches[0] : e;
            return { x: (p.clientX - r.left) * (canvas.width / r.width), y: (p.clientY - r.top) * (canvas.height / r.height) };
        }
        function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
        function move(e) { if (!drawing) return; var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', function () { drawing = false; });
        canvas.addEventListener('touchstart', start, {passive:false});
        canvas.addEventListener('touchmove', move, {passive:false});
        canvas.addEventListener('touchend', function () { drawing = false; });
        document.getElementById('clear').onclick = function () { ctx.clearRect(0, 0, canvas.width, canvas.height); };
        document.querySelector('form').onsubmit = function () { document.getElementById('signature').value = canvas.toDataURL('image/png'); };
    </script>
@else
    <div class="join-card">
        <h1>Application received</h1>
        <p>Reference <strong>{{ $application->reference }}</strong></p>
        <p>Status: {{ $application->status }}</p>
        <p>Check it later with this reference and the same phone.</p>
    </div>
@endif
</div>
@endsection

@extends('layout.main')
@section('content')

@if($errors->any())
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        @foreach($errors->all() as $error) {{ $error }} <br> @endforeach
    </div>
@endif
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('not_permitted') }}
    </div>
@endif

	<section>
	    <div class="container-fluid">
	        <div class="card">
            <div class="card-header"><h4>Create Invitation</h4></div>
            <div class="card-body">
                <form action="{{ route('online_invitation.invitations.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Event *</label>
                                <select name="event_id" id="event_id" class="form-control selectpicker" required data-live-search="true" title="Select event...">
                                    <option value="">Select Event</option>
                                    @foreach($events as $e)
                                        <option value="{{ $e->id }}" {{ old('event_id') == $e->id ? 'selected' : '' }}>
                                            {{ $e->name }} ({{ $e->event_at }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Send To *</label>
                                <select name="recipient_mode" id="recipient_mode" class="form-control" required>
                                    <option value="customers" {{ old('recipient_mode', 'customers') === 'customers' ? 'selected' : '' }}>Customers (Multiple)</option>
                                    <option value="customer_group" {{ old('recipient_mode') === 'customer_group' ? 'selected' : '' }}>Customer Group</option>
                                    <option value="users" {{ old('recipient_mode') === 'users' ? 'selected' : '' }}>Users (Staff / Interns)</option>
                                    <option value="csv" {{ old('recipient_mode') === 'csv' ? 'selected' : '' }}>Import CSV</option>
                                </select>
                            </div>
                        </div>
                    </div>

	                    <div class="row">
	                        <div class="col-md-6">
	                            <div class="form-group">
	                                <label>Invitation Type *</label>
	                                <select name="category_id" id="category_id" class="form-control selectpicker" required data-live-search="true" title="Select invitation type...">
	                                    <option value="">Select Invitation Type</option>
	                                    @foreach($categories as $cat)
	                                        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
	                                    @endforeach
	                                </select>
	                                <small class="form-text text-muted">Invitation types will be limited to the selected event (if configured).</small>
	                            </div>
	                        </div>
	                        <div class="col-md-6">
	                            <div class="form-group">
	                                <label>Live Preview</label>
	                                <div id="oi-preview" class="card" style="border-radius: 10px; overflow: hidden; border: 1px solid #e9ecef;">
	                                    <style>
	                                        /* Keep live-preview colors consistent with saved invitation:
	                                           font color applies to all text; border color applies only to borders. */
	                                        #oi-preview-canvas { color: #f3e7c1; }
	                                        #oi-preview-canvas * { color: inherit !important; }
	                                    </style>
	                                    <div id="oi-preview-canvas" style="
	                                        height: 360px;
	                                        position: relative;
	                                        background: #111;
	                                        background-repeat: no-repeat;
	                                        background-position: center center;
	                                        background-size: cover;
	                                        font-family: 'Palatino Linotype', 'Book Antiqua', Palatino, 'DejaVu Serif', 'DejaVu Sans', serif;
	                                    ">
	                                        <div style="position:absolute; inset:0; background: rgba(0,0,0,0.45);"></div>

	                                        <div id="oi-preview-border-outer" style="position:absolute; inset:14px; border: 2px solid #c8a75e; padding: 8px;">
	                                            <div id="oi-preview-border-inner" style="position:absolute; inset:8px; border: 1px solid #c8a75e;"></div>
	                                        </div>

	                                        <div id="oi-preview-text" style="position:absolute; inset:26px; padding-right: 120px; padding-bottom: 120px; box-sizing: border-box;">
	                                            <div id="oi-preview-title" style="text-align:center; letter-spacing:2px; font-size: 26px; font-weight:700; text-transform:uppercase;">Invitation</div>
	                                            <div style="text-align:center; letter-spacing:2px; font-size: 14px; margin-top:2px; text-transform:uppercase;">Invitation</div>

	                                            <div id="oi-preview-dear" style="text-align:center; font-size: 18px; font-style: italic; margin-top: 14px;">
	                                                Dear Guest,
	                                            </div>
	                                            <div id="oi-preview-message" style="text-align:center; font-size: 12px; margin-top: 6px;">
	                                                You are invited as a Guest to our Event
	                                            </div>
	                                            <div id="oi-preview-optional-message" style="text-align:center; font-size: 10px; margin-top: 6px;">
	                                            </div>

	                                            <div id="oi-preview-details" style="margin: 12px auto 0 auto; width: 86%; border-top: 1px solid #c8a75e; border-bottom: 1px solid #c8a75e; padding: 10px 0;">
	                                                <div style="display:flex; justify-content:space-between; font-size: 12px; padding: 4px 0;">
	                                                    <span style="color: rgba(243,231,193,0.8);">Date:</span>
	                                                    <span id="oi-preview-date" style="font-weight:600;">—</span>
	                                                </div>
	                                                <div style="display:flex; justify-content:space-between; font-size: 12px; padding: 4px 0;">
	                                                    <span style="color: rgba(243,231,193,0.8);">Venue:</span>
	                                                    <span id="oi-preview-venue" style="font-weight:600;">—</span>
	                                                </div>
	                                                <div style="display:flex; justify-content:space-between; font-size: 11px; padding: 4px 0;">
	                                                    <span style="color: rgba(243,231,193,0.8);">Phone:</span>
	                                                    <span id="oi-preview-phone" style="font-weight:600;">—</span>
	                                                </div>
	                                                <div id="oi-preview-email-row" style="display:flex; justify-content:space-between; font-size: 11px; padding: 4px 0;">
	                                                    <span style="color: rgba(243,231,193,0.8);">Email:</span>
	                                                    <span id="oi-preview-email" style="font-weight:600;">—</span>
	                                                </div>
	                                            </div>

	                                                <div style="position:absolute; left: 0; right: 0; bottom: 0; padding-bottom: 6px; text-align:center; font-size: 9px;">
	                                                Recipients: <span id="oi-preview-recipients">—</span>
	                                            </div>
	                                        </div>

	                                        <div style="position:absolute; left: 50%; transform: translateX(-50%); bottom: 22px; width: 220px; text-align:center;">
	                                            <div id="oi-preview-qr-box" style="width: 88px; height: 88px; margin: 0 auto; background: rgba(0,0,0,0.35); border: 2px solid rgba(200,167,94,0.85); padding: 6px; box-sizing:border-box;">
	                                                <div id="oi-preview-qr" style="width: 100%; height: 100%; background:#fff; display:flex; align-items:center; justify-content:center; border-radius: 2px;"></div>
	                                            </div>
	                                            <div style="margin-top: 6px; font-size: 9px; color: rgba(243,231,193,0.85);">RSVP:</div>
	                                            <div id="oi-preview-rsvp" style="font-size: 8px; word-break: break-word; margin-top: 2px; color: rgba(243,231,193,0.85);">{{ rtrim(env('APP_URL'), '/') }}/online-invitation/invite/xxxxx</div>
	                                        </div>
	                                    </div>
	                                </div>
	                            </div>
	                        </div>
	                    </div>

	                    <div class="row">
	                        <div class="col-md-12">
	                            <div class="form-group">
	                                <label>Optional Message</label>
	                                <textarea name="message" id="message" class="form-control" rows="2" placeholder="Write a short message that will appear on the invitation...">{{ old('message') }}</textarea>
	                            </div>
	                        </div>
	                    </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>RSVP</label>
                                    <input type="text" name="rsvp" id="rsvp" class="form-control" value="{{ old('rsvp') }}" placeholder="e.g. 0300-1234567 or a URL">
                                    <small class="form-text text-muted">Shown below the QR code. If empty, the invitation link is shown.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Border Color</label>
                                    <input type="color" name="border_color" id="border_color" class="form-control" value="{{ old('border_color', '#c8a75e') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Font Color</label>
                                    <input type="color" name="font_color" id="font_color" class="form-control" value="{{ old('font_color', '#f3e7c1') }}">
                                </div>
                            </div>
                        </div>

                    <div class="row" id="mode-customers">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Customers *</label>
	                                <select name="customer_ids[]" class="form-control selectpicker" multiple data-live-search="true" data-actions-box="true" title="Select customers...">
	                                    @foreach($customers as $c)
	                                        @php($label = $c->name)
	                                        @if($c->phone_number)
	                                            @php($label = $label . ' (' . $c->phone_number . ')')
	                                        @endif
	                                        <option value="{{ $c->id }}" data-phone="{{ $c->phone_number }}" data-email="{{ $c->email }}" {{ in_array($c->id, old('customer_ids', [])) ? 'selected' : '' }}>{{ $label }}</option>
	                                    @endforeach
	                                </select>
                                <small class="form-text text-muted">Tip: use search box to quickly find customers.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="mode-customer-group" style="display:none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Customer Group *</label>
                                <select name="customer_group_id" class="form-control selectpicker" data-live-search="true" title="Select customer group...">
                                    <option value="">Select Customer Group</option>
                                    @foreach($customerGroups as $g)
                                        <option value="{{ $g->id }}" {{ old('customer_group_id') == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="mode-users" style="display:none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Users *</label>
                                <select name="user_ids[]" class="form-control selectpicker" multiple data-live-search="true" data-actions-box="true" title="Select users...">
                                    @foreach(($users ?? []) as $u)
                                        @php($label = $u->name)
                                        @if($u->phone)
                                            @php($label = $label . ' (' . $u->phone . ')')
                                        @endif
                                        <option value="{{ $u->id }}" {{ in_array($u->id, old('user_ids', [])) ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Tip: use Select all / Deselect all in the picker.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="mode-csv" style="display:none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>CSV File *</label>
                                <input type="file" name="recipient_csv" class="form-control" accept=".csv,text/csv">
                                <small class="form-text text-muted">CSV columns: <strong>name</strong>, <strong>number</strong>, <strong>email</strong>. Header row supported.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <button class="btn btn-primary" type="submit">{{ trans('file.submit') }}</button>
                        <a class="btn btn-link" href="{{ route('online_invitation.invitations.index') }}">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    $("ul#online_invitation #online-invitation-invitation-menu").addClass("active");

    var oiEventPreviewData = @json($eventPreviewData);
    var oiBaseUrl = "{{ rtrim(url('/'), '/') }}";

    function toggleRecipientMode() {
        var mode = $('#recipient_mode').val();
        $('#mode-customers').toggle(mode === 'customers');
        $('#mode-customer-group').toggle(mode === 'customer_group');
        $('#mode-users').toggle(mode === 'users');
        $('#mode-csv').toggle(mode === 'csv');
    }

    function setPreviewBackground(background) {
        var el = document.getElementById('oi-preview-canvas');
        if (!el) return;
        background = (background || '').toString().trim();
        el.style.backgroundColor = '#111';
        el.style.backgroundImage = '';
        el.style.backgroundRepeat = 'no-repeat';
        el.style.backgroundPosition = 'center center';
        el.style.backgroundSize = 'cover';

        if (!background) return;

        var urlMatch = background.match(/url\(([^)]+)\)/i);
        var ref = urlMatch ? urlMatch[1] : background;
        ref = ref.replace(/^['"]|['"]$/g, '').trim();
        if (ref.match(/^https?:\/\//i)) {
            try {
                var u = new URL(ref);
                if (u.host === window.location.host && !u.pathname.match(/^\/public(\/|$)/i)) {
                    u.pathname = '/public' + (u.pathname.indexOf('/') === 0 ? u.pathname : '/' + u.pathname);
                    ref = u.toString();
                }
            } catch (e) {}
            el.style.backgroundImage = "url('" + ref + "')";
            el.style.backgroundColor = '#111';
            return;
        }
        if (ref.indexOf('/') === 0) {
            // Convert /images/... to fully-qualified asset URL (handles ASSET_URL like https://domain.com/public)
            var url = ref.match(/^\/public(\/|$)/i) ? (oiBaseUrl + ref) : (oiBaseUrl + '/public' + ref);
            el.style.backgroundImage = "url('" + url + "')";
            el.style.backgroundColor = '#f2f4f8';
            return;
        }

        // assume CSS color
        el.style.backgroundImage = '';
        el.style.backgroundColor = background || '#111';
    }

    function refreshCategoriesForEvent(eventId) {
        var allowed = [];
        if (eventId && oiEventPreviewData[eventId] && oiEventPreviewData[eventId].categories) {
            allowed = oiEventPreviewData[eventId].categories;
        }

        var categorySelect = $('#category_id');
        if (!categorySelect.length) return;

        var current = categorySelect.val();
        categorySelect.empty();
        categorySelect.append('<option value="">Select Invitation Type</option>');

        if (allowed.length) {
            for (var i = 0; i < allowed.length; i++) {
                var c = allowed[i];
                categorySelect.append('<option value="' + c.id + '">' + c.name + '</option>');
            }
        } else {
            // If event has no configured categories, keep global list as fallback
            @foreach($categories as $cat)
                categorySelect.append('<option value="{{ $cat->id }}">{{ $cat->name }}</option>');
            @endforeach
        }

        if (current) {
            categorySelect.val(current);
        }
        categorySelect.selectpicker('refresh');
    }

    function refreshPreview() {
        var eventId = $('#event_id').val();
        var eventData = eventId ? oiEventPreviewData[eventId] : null;

        var eventName = eventData ? (eventData.name || 'Event') : 'Event';
        var eventAt = eventData ? (eventData.event_at || '—') : '—';
        var venue = eventData ? (eventData.location || '—') : '—';
        setPreviewBackground(eventData && eventData.template ? eventData.template.background : '');

        var catText = $('#category_id option:selected').text() || '';
        var inviteType = (catText && catText !== 'Select Invitation Type') ? catText : 'Guest';
        $('#oi-preview-title').text(inviteType);
        $('#oi-preview-message').text('You are invited as a ' + inviteType + ' to our ' + eventName);
        $('#oi-preview-date').text(eventAt);
        $('#oi-preview-venue').text(venue);

        var mode = $('#recipient_mode').val();
        var recipientText = '—';
        var dearName = 'Guest';
        var phone = '—';
        var email = '';
        if (mode === 'customers') {
            var selected = $('select[name="customer_ids[]"]').val() || [];
            recipientText = selected.length ? (selected.length + ' customer(s) selected') : '—';
            if (selected.length === 1) {
                var optText = $('select[name="customer_ids[]"] option[value="' + selected[0] + '"]').text() || '';
                dearName = optText.split('(')[0].trim() || 'Guest';

                var opt = $('select[name="customer_ids[]"] option[value="' + selected[0] + '"]');
                phone = (opt.data('phone') || '—').toString();
                email = (opt.data('email') || '').toString().trim();
                if (email === '—' || email === '-' || email.toLowerCase() === 'n/a' || email.toLowerCase() === 'na' || email.toLowerCase() === 'null' || email.toLowerCase() === 'none') {
                    email = '';
                }
            }
        } else if (mode === 'customer_group') {
            var g = $('select[name="customer_group_id"] option:selected').text() || '';
            recipientText = g && g !== 'Select Customer Group' ? ('Group: ' + g) : '—';
            dearName = 'Guest';
        } else if (mode === 'csv') {
            var fileInput = document.querySelector('input[name="recipient_csv"]');
            recipientText = (fileInput && fileInput.files && fileInput.files.length) ? ('CSV: ' + fileInput.files[0].name) : '—';
            dearName = 'Guest';
        }
        $('#oi-preview-recipients').text(recipientText);
        $('#oi-preview-dear').text('Dear ' + dearName + ',');
        $('#oi-preview-phone').text(phone || '—');
        $('#oi-preview-email').text(email);
        $('#oi-preview-email-row').toggle(!!email);

        var rsvp = ($('#rsvp').val() || '').toString().trim();
        var rsvpDisplay = rsvp || (oiBaseUrl + '/online-invitation/invite/xxxxx');
        $('#oi-preview-rsvp').text(rsvpDisplay);

        var borderColor = ($('#border_color').val() || '#c8a75e').toString();
        $('#oi-preview-border-outer').css('border-color', borderColor);
        $('#oi-preview-border-inner').css('border-color', borderColor);
        $('#oi-preview-qr-box').css('border-color', borderColor);
        $('#oi-preview-details').css('border-top-color', borderColor).css('border-bottom-color', borderColor);

        var fontColor = ($('#font_color').val() || '#f3e7c1').toString();
        $('#oi-preview-canvas').css('color', fontColor);
        $('#oi-preview-text').css('color', fontColor);
        $('#oi-preview-title').css('color', fontColor);

        var msg = ($('#message').val() || '').toString().trim();
        if (msg) {
            $('#oi-preview-optional-message').text(msg);
        } else {
            $('#oi-preview-optional-message').text('');
        }

        renderFakeQrCode(document.getElementById('oi-preview-qr'), [eventId, $('#category_id').val(), dearName, msg].join('|'));
    }

    function renderFakeQrCode(container, seedText) {
        if (!container) return;
        seedText = (seedText || '').toString();

        // Simple deterministic hash (FNV-1a) to seed a PRNG
        var hash = 2166136261;
        for (var i = 0; i < seedText.length; i++) {
            hash ^= seedText.charCodeAt(i);
            hash = (hash * 16777619) >>> 0;
        }

        function rand() {
            // xorshift32
            hash ^= (hash << 13) >>> 0;
            hash ^= (hash >>> 17) >>> 0;
            hash ^= (hash << 5) >>> 0;
            return (hash >>> 0) / 4294967296;
        }

        var size = 21; // QR version-1 like grid
        var module = 3; // px per module in viewBox space
        var vb = size * module;
        var rects = [];

        function isInFinder(x, y) {
            var inTL = x < 7 && y < 7;
            var inTR = x >= size - 7 && y < 7;
            var inBL = x < 7 && y >= size - 7;
            return inTL || inTR || inBL;
        }

        function isInTiming(x, y) {
            return (y === 6 && x >= 8 && x <= size - 9) || (x === 6 && y >= 8 && y <= size - 9);
        }

        function finderModule(x, y) {
            var fx = x % 7;
            var fy = y % 7;
            var onBorder = fx === 0 || fx === 6 || fy === 0 || fy === 6;
            var onInner = fx >= 2 && fx <= 4 && fy >= 2 && fy <= 4;
            return onBorder || onInner;
        }

        // background
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="78" height="78" viewBox="0 0 ' + vb + ' ' + vb + '">';
        svg += '<rect width="' + vb + '" height="' + vb + '" fill="#fff"/>';

        for (var y = 0; y < size; y++) {
            for (var x = 0; x < size; x++) {
                var dark = false;

                if (isInFinder(x, y)) {
                    // map to local finder coords
                    var ox = x;
                    var oy = y;
                    if (x >= size - 7 && y < 7) {
                        ox = x - (size - 7);
                        oy = y;
                    } else if (x < 7 && y >= size - 7) {
                        ox = x;
                        oy = y - (size - 7);
                    }
                    dark = finderModule(ox, oy);
                } else if (isInTiming(x, y)) {
                    dark = ((x + y) % 2) === 0;
                } else {
                    // random modules, but keep a quiet zone-ish margin inside the svg by reducing density
                    dark = rand() > 0.62;
                }

                if (dark) {
                    rects.push('<rect x="' + (x * module) + '" y="' + (y * module) + '" width="' + module + '" height="' + module + '" fill="#111"/>');
                }
            }
        }

        svg += rects.join('');
        svg += '</svg>';
        container.innerHTML = svg;
    }

    $('#recipient_mode').on('change', toggleRecipientMode);
    $('#recipient_mode').on('change', refreshPreview);
    $('#event_id').on('change', function () {
        refreshCategoriesForEvent($(this).val());
        refreshPreview();
    });
    $('#category_id').on('change', refreshPreview);
    $('select[name="customer_ids[]"]').on('changed.bs.select', refreshPreview);
    $('select[name="customer_group_id"]').on('changed.bs.select', refreshPreview);
    $('input[name="recipient_csv"]').on('change', refreshPreview);
    $('#message').on('input', refreshPreview);
    $('#rsvp').on('input', refreshPreview);
    $('#border_color').on('input', refreshPreview);
    $('#font_color').on('input', refreshPreview);

    toggleRecipientMode();
    refreshCategoriesForEvent($('#event_id').val());
    refreshPreview();
</script>

@endsection

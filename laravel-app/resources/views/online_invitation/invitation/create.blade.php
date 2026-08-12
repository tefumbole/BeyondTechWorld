@extends('layout.main')
@section('content')
@php $oiTab = 'create'; @endphp
@include('online_invitation.partials.tabs')

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
                                    <option value="directory" {{ old('recipient_mode', 'directory') === 'directory' ? 'selected' : '' }}>Directory (Groups &amp; People)</option>
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

                    <style>
                        .oi-pill {
                            border:0; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600;
                            background:#f1f5f9; color:#334155; cursor:pointer; margin:0 4px 6px 0;
                        }
                        .oi-pill.active { background:#0b3f90; color:#fff; }
                        .oi-pill-outline {
                            border:1px solid #0b3f90; color:#0b3f90; background:#fff; border-radius:8px;
                            padding:8px 12px; font-weight:600; font-size:13px; cursor:pointer; white-space:nowrap;
                        }
                        .oi-user-list { max-height:260px; overflow:auto; border:1px solid #e3e9f4; border-radius:10px; background:#fff; }
                        .oi-user-item {
                            display:block; width:100%; text-align:left; padding:10px 12px; border:0;
                            border-bottom:1px solid #f0f3f8; background:#fff; cursor:pointer;
                        }
                        .oi-user-item:hover, .oi-user-item.selected { background:#f0f6ff; }
                        .oi-user-item .meta { color:#6b7280; font-size:12px; }
                        .oi-chip {
                            display:inline-flex; align-items:center; gap:6px; border:1px solid #0b3f90; color:#0b3f90;
                            background:#eef4ff; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:600; margin:2px;
                        }
                        .oi-chip button { border:0; background:transparent; color:#0b3f90; font-weight:800; cursor:pointer; }
                        .oi-field { width:100%; border:1px solid #d7deea; border-radius:8px; padding:9px 12px; font-size:14px; }
                    </style>

                    <div class="row" id="mode-directory">
                        <div class="col-md-12">
                            <div class="form-group mb-2">
                                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                                    <label class="mb-0">Recipients *</label>
                                    <div>
                                        <small class="text-muted">Selected</small>
                                        <strong id="oi-recipient-count" style="color:#0b3f90;font-size:1.15rem;margin-left:6px;">0</strong>
                                    </div>
                                </div>
                                <p class="text-muted small mb-2">Pick entire customer groups and/or individuals (customers &amp; staff), same style as Task Manager.</p>
                                <div class="mb-2">
                                    <button type="button" class="oi-pill active oi-rf" data-role="all">All</button>
                                    <button type="button" class="oi-pill oi-rf" data-role="groups">Groups</button>
                                    <button type="button" class="oi-pill oi-rf" data-role="customers">Customers</button>
                                    <button type="button" class="oi-pill oi-rf" data-role="staff">Staff</button>
                                </div>
                                <div class="d-flex mb-2 flex-wrap" style="gap:8px;">
                                    <input type="search" class="oi-field" id="oi-rsearch" placeholder="Search groups, names, phone, email…" style="flex:1;min-width:180px;">
                                    <button type="button" class="oi-pill-outline" id="oi-rselect-all">Select everyone</button>
                                    <button type="button" class="oi-pill-outline" id="oi-rdeselect-all">Deselect all</button>
                                </div>
                                <div class="oi-user-list" id="oi-rlist"></div>
                                <div class="mt-2" id="oi-rchips"></div>
                                <div id="oi-rhiddens"></div>
                                <small class="text-danger d-none" id="oi-rerr">Pick at least one group or person.</small>
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
    window.OI_PEOPLE = @json($directoryPeople ?? []);
    window.OI_PRESELECT = @json(old('recipient_ids', []));

    var oiSelected = (window.OI_PRESELECT || []).slice();
    var oiRole = 'all';

    function oiEsc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function oiMatchesRole(u, roleFilter) {
        var source = (u.source || '').toLowerCase();
        var role = (u.role || '').toLowerCase();
        if (roleFilter === 'groups') return source === 'group' || role === 'group';
        if (roleFilter === 'customers') return source === 'customer' || role === 'customer';
        if (roleFilter === 'staff') return source === 'staff' || role === 'staff';
        return true;
    }

    function oiFilterPeople(query, roleFilter) {
        var q = (query || '').toLowerCase().trim();
        return (window.OI_PEOPLE || []).filter(function (u) {
            if (!oiMatchesRole(u, roleFilter)) return false;
            if (!q) return true;
            return (u.name || '').toLowerCase().indexOf(q) !== -1
                || (u.email || '').toLowerCase().indexOf(q) !== -1
                || (u.phone || '').toLowerCase().indexOf(q) !== -1
                || (u.meta || '').toLowerCase().indexOf(q) !== -1
                || (u.source || '').toLowerCase().indexOf(q) !== -1;
        });
    }

    function oiRecipientEstimate() {
        var map = {};
        (window.OI_PEOPLE || []).forEach(function (u) { map[u.id] = u; });
        var total = 0;
        oiSelected.forEach(function (id) {
            var u = map[id];
            if (!u) { total += 1; return; }
            if ((u.source || '').toLowerCase() === 'group' || (u.role || '').toLowerCase() === 'group') {
                total += parseInt(u.member_count || 0, 10) || 0;
            } else {
                total += 1;
            }
        });
        return total;
    }

    function oiSyncHiddens() {
        var box = document.getElementById('oi-rhiddens');
        if (!box) return;
        box.innerHTML = oiSelected.map(function (id) {
            return '<input type="hidden" name="recipient_ids[]" value="' + oiEsc(id) + '">';
        }).join('');
        var countEl = document.getElementById('oi-recipient-count');
        if (countEl) countEl.textContent = String(oiSelected.length);
        var err = document.getElementById('oi-rerr');
        if (err) err.classList.toggle('d-none', oiSelected.length > 0 || $('#recipient_mode').val() !== 'directory');
    }

    function oiRenderChips() {
        var el = document.getElementById('oi-rchips');
        if (!el) return;
        var map = {};
        (window.OI_PEOPLE || []).forEach(function (u) { map[u.id] = u; });
        el.innerHTML = oiSelected.map(function (id) {
            var u = map[id] || { name: id, source: '' };
            var label = (u.source ? u.source + ': ' : '') + (u.name || id);
            return '<span class="oi-chip" data-id="' + oiEsc(id) + '">' + oiEsc(label)
                + ' <button type="button" title="Remove" aria-label="Remove">×</button></span>';
        }).join('');
        el.querySelectorAll('.oi-chip button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                oiToggle(btn.parentNode.getAttribute('data-id'));
            });
        });
    }

    function oiRenderList() {
        var el = document.getElementById('oi-rlist');
        if (!el) return;
        var people = oiFilterPeople(document.getElementById('oi-rsearch').value, oiRole);
        el.innerHTML = people.map(function (u) {
            var sel = oiSelected.indexOf(u.id) !== -1 ? ' selected' : '';
            var meta = u.meta || ((u.phone || '') + ((u.phone && u.email) ? ' · ' : '') + (u.email || ''));
            return '<button type="button" class="oi-user-item' + sel + '" data-id="' + oiEsc(u.id) + '">'
                + '<div class="font-weight-bold">' + oiEsc(u.name || 'Untitled')
                + (u.source ? ' <span class="badge badge-light">' + oiEsc(u.source) + '</span>' : '')
                + '</div>'
                + '<div class="meta">' + oiEsc(meta || '—') + '</div>'
                + '</button>';
        }).join('') || '<div class="p-3 text-muted small text-center">No matches.</div>';
        el.querySelectorAll('.oi-user-item').forEach(function (item) {
            item.addEventListener('click', function () { oiToggle(item.getAttribute('data-id')); });
        });
    }

    function oiToggle(id) {
        if (!id) return;
        var idx = oiSelected.indexOf(id);
        if (idx === -1) oiSelected.push(id);
        else oiSelected.splice(idx, 1);
        oiSyncHiddens();
        oiRenderChips();
        oiRenderList();
        refreshPreview();
    }

    function oiInitPicker() {
        document.querySelectorAll('.oi-rf').forEach(function (btn) {
            btn.addEventListener('click', function () {
                oiRole = btn.getAttribute('data-role') || 'all';
                document.querySelectorAll('.oi-rf').forEach(function (b) { b.classList.toggle('active', b === btn); });
                oiRenderList();
            });
        });
        var search = document.getElementById('oi-rsearch');
        if (search) {
            search.addEventListener('input', function () { oiRenderList(); });
        }
        var selectAll = document.getElementById('oi-rselect-all');
        if (selectAll) {
            selectAll.addEventListener('click', function () {
                oiFilterPeople(document.getElementById('oi-rsearch').value, oiRole).forEach(function (u) {
                    if (oiSelected.indexOf(u.id) === -1) oiSelected.push(u.id);
                });
                oiSyncHiddens();
                oiRenderChips();
                oiRenderList();
                refreshPreview();
            });
        }
        var deselectAll = document.getElementById('oi-rdeselect-all');
        if (deselectAll) {
            deselectAll.addEventListener('click', function () {
                oiSelected = [];
                oiSyncHiddens();
                oiRenderChips();
                oiRenderList();
                refreshPreview();
            });
        }
        oiSyncHiddens();
        oiRenderChips();
        oiRenderList();
    }

    function toggleRecipientMode() {
        var mode = $('#recipient_mode').val();
        $('#mode-directory').toggle(mode === 'directory');
        $('#mode-csv').toggle(mode === 'csv');
        oiSyncHiddens();
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
        if (mode === 'directory') {
            var estimate = oiRecipientEstimate();
            recipientText = oiSelected.length
                ? (oiSelected.length + ' selection(s) ≈ ' + estimate + ' recipient(s)')
                : '—';
            if (oiSelected.length === 1) {
                var map = {};
                (window.OI_PEOPLE || []).forEach(function (u) { map[u.id] = u; });
                var one = map[oiSelected[0]];
                if (one && (one.source || '').toLowerCase() !== 'group') {
                    dearName = (one.name || 'Guest').toString().trim() || 'Guest';
                    phone = (one.phone || '—').toString();
                    email = (one.email || '').toString().trim();
                    if (email === '—' || email === '-' || email.toLowerCase() === 'n/a' || email.toLowerCase() === 'na' || email.toLowerCase() === 'null' || email.toLowerCase() === 'none') {
                        email = '';
                    }
                }
            }
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
    $('input[name="recipient_csv"]').on('change', refreshPreview);
    $('#message').on('input', refreshPreview);
    $('#rsvp').on('input', refreshPreview);
    $('#border_color').on('input', refreshPreview);
    $('#font_color').on('input', refreshPreview);

    $('form').on('submit', function (e) {
        if ($('#recipient_mode').val() === 'directory' && oiSelected.length === 0) {
            e.preventDefault();
            $('#oi-rerr').removeClass('d-none');
            return false;
        }
    });

    oiInitPicker();
    toggleRecipientMode();
    refreshCategoriesForEvent($('#event_id').val());
    refreshPreview();
</script>

@endsection

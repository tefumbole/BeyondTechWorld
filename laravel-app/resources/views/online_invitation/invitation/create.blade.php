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
	                                <label>Live Preview <small class="text-muted">(matches delivered PDF)</small></label>
	                                <div id="oi-preview" class="card" style="border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 8px 24px rgba(15,23,42,.08);">
	                                    <style>
	                                        #oi-preview-canvas { color: #f3e7c1; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
	                                        #oi-preview-canvas .oi-pv { color: inherit; }
	                                    </style>
	                                    <div id="oi-preview-canvas" style="
	                                        height: 560px;
	                                        position: relative;
	                                        background: #111;
	                                        background-repeat: no-repeat;
	                                        background-position: center center;
	                                        background-size: cover;
	                                    ">
	                                        <div style="position:absolute; inset:0; background: rgba(0,0,0,0.42);"></div>

	                                        <div id="oi-preview-border-outer" style="position:absolute; inset:12px; border: 3px solid #c8a75e; padding: 6px; box-sizing:border-box;">
	                                            <div id="oi-preview-border-inner" style="position:absolute; inset:6px; border: 1px solid #c8a75e;"></div>
	                                        </div>

	                                        <div id="oi-preview-text" class="oi-pv" style="position:absolute; inset:28px 24px 18px 24px; overflow:hidden; box-sizing: border-box; display:flex; flex-direction:column;">
	                                            <div id="oi-preview-title" class="oi-pv" style="text-align:center; letter-spacing:2px; font-size: 20px; font-weight:700; text-transform:uppercase;">Guest</div>
	                                            <div class="oi-pv" style="text-align:center; letter-spacing:3px; font-size: 10px; font-weight:600; text-transform:uppercase; opacity:.9; margin-bottom:8px;">Invitation</div>

	                                            <div id="oi-preview-dear" class="oi-pv" style="text-align:center; font-size: 15px; font-style: italic; margin: 4px 0 8px;">
	                                                Dear Guest,
	                                            </div>
	                                            <div id="oi-preview-message" class="oi-pv" style="text-align:center; font-size: 12px; font-weight:500; line-height:1.35;">
	                                                You are cordially invited as Guest to
	                                            </div>
	                                            <div id="oi-preview-event" class="oi-pv" style="text-align:center; font-size: 18px; font-weight:800; letter-spacing:1px; text-transform:uppercase; line-height:1.15; margin: 4px 0 8px; padding-bottom:8px; border-bottom: 2px solid #c8a75e;">
	                                                Event
	                                            </div>
	                                            <div id="oi-preview-optional-message" class="oi-pv" style="text-align:center; font-size: 11px; margin-bottom:8px;"></div>

	                                            <div id="oi-preview-details" class="oi-pv" style="margin: 0 auto; width: 92%; border: 2px solid #c8a75e; border-radius: 6px; padding: 10px 12px; background: rgba(0,0,0,0.22);">
	                                                <div style="text-align:center; padding: 4px 0;">
	                                                    <div class="oi-pv" style="font-size: 9px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; opacity:.85;">Date</div>
	                                                    <div id="oi-preview-date" class="oi-pv" style="font-size: 14px; font-weight:800;">—</div>
	                                                </div>
	                                                <div id="oi-preview-venue-row" style="text-align:center; padding: 8px 0 4px; border-top: 1px solid #c8a75e; margin-top:4px;">
	                                                    <div class="oi-pv" style="font-size: 9px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; opacity:.85;">Location</div>
	                                                    <div id="oi-preview-venue" class="oi-pv" style="font-size: 14px; font-weight:800;">—</div>
	                                                </div>
	                                            </div>

	                                            <div style="flex:1;"></div>

	                                            <div id="oi-preview-qr-box" style="width: 72px; height: 72px; margin: 10px auto 0; background:#fff; border: 3px solid #c8a75e; padding: 4px; box-sizing:border-box;">
	                                                <div id="oi-preview-qr" style="width: 100%; height: 100%; background:#fff; display:flex; align-items:center; justify-content:center;"></div>
	                                            </div>

	                                            <div id="oi-preview-footer" class="oi-pv" style="text-align:center; font-size: 11px; font-weight:700; margin-top: 10px; padding-top: 8px; border-top: 1px solid #c8a75e; line-height:1.35;">
	                                                Please present this invitation at the venue.<br>This invitation is generated electronically.
	                                            </div>

	                                            <div style="text-align:center; margin-top: 8px;">
	                                                <div id="oi-preview-rsvp-label" class="oi-pv" style="font-size: 10px; font-weight:800; letter-spacing:2px; text-transform:uppercase;">RSVP</div>
	                                                <div id="oi-preview-rsvp" class="oi-pv" style="font-size: 13px; font-weight:800; word-break: break-word; line-height:1.25; margin-top:2px;">{{ rtrim(env('APP_URL'), '/') }}/online-invitation/invite/xxxxx</div>
	                                            </div>

	                                            <div class="oi-pv" style="text-align:center; font-size: 9px; opacity:.75; margin-top:6px;">
	                                                Recipients: <span id="oi-preview-recipients">—</span>
	                                            </div>
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
                                    <input type="text" name="rsvp" id="rsvp" class="form-control" value="{{ old('rsvp') }}" placeholder="e.g. Sir. Jude Wandas or a phone / URL">
                                    <small class="form-text text-muted">Shown large at the bottom of the invitation. If empty, the invite link is shown.</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Border Color</label>
                                    <div class="oi-color-field">
                                        <input type="color" id="border_color_picker" value="{{ old('border_color', '#c8a75e') }}" title="Pick border color">
                                        <input type="text" name="border_color" id="border_color" class="form-control" value="{{ old('border_color', '#c8a75e') }}" maxlength="7" placeholder="#c8a75e" spellcheck="false">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Font Color</label>
                                    <div class="oi-color-field">
                                        <input type="color" id="font_color_picker" value="{{ old('font_color', '#f3e7c1') }}" title="Pick font color">
                                        <input type="text" name="font_color" id="font_color" class="form-control" value="{{ old('font_color', '#f3e7c1') }}" maxlength="7" placeholder="#f3e7c1" spellcheck="false">
                                    </div>
                                </div>
                            </div>
                        </div>

                    <style>
                        .oi-color-field {
                            display:flex; align-items:center; gap:10px;
                            background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;
                            padding:8px 10px;
                        }
                        .oi-color-field input[type="color"] {
                            -webkit-appearance:none; appearance:none; border:0; padding:0; width:42px; height:42px;
                            border-radius:10px; background:transparent; cursor:pointer; flex:0 0 auto;
                        }
                        .oi-color-field input[type="color"]::-webkit-color-swatch-wrapper { padding:0; }
                        .oi-color-field input[type="color"]::-webkit-color-swatch {
                            border:2px solid #fff; border-radius:10px;
                            box-shadow:0 0 0 1px #cbd5e1, 0 2px 8px rgba(15,23,42,.12);
                        }
                        .oi-color-field input[type="text"] {
                            border:0; background:transparent; font-weight:700; letter-spacing:.04em;
                            text-transform:uppercase; color:#0f172a; padding:0;
                        }
                        .oi-color-field input[type="text"]:focus { outline:none; box-shadow:none; }
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
    $("ul#online_invitation #online-invitation-create-menu").addClass("active");

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

    function normalizeHexColor(value, fallback) {
        var v = (value || '').toString().trim();
        if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)) {
            if (v.length === 4) {
                v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
            }
            return v.toLowerCase();
        }
        return fallback;
    }

    function syncColorPair(textId, pickerId, fallback) {
        var $text = $('#' + textId);
        var $picker = $('#' + pickerId);
        var hex = normalizeHexColor($text.val(), fallback);
        $text.val(hex);
        $picker.val(hex);
        return hex;
    }

    function bindColorPair(textId, pickerId, fallback) {
        $('#' + pickerId).on('input change', function () {
            $('#' + textId).val($(this).val());
            refreshPreview();
        });
        $('#' + textId).on('input change blur', function () {
            var hex = normalizeHexColor($(this).val(), fallback);
            $(this).val(hex);
            $('#' + pickerId).val(hex);
            refreshPreview();
        });
    }

    function refreshPreview() {
        var eventId = $('#event_id').val();
        var eventData = eventId ? oiEventPreviewData[eventId] : null;

        var eventName = eventData ? (eventData.name || 'Event') : 'Event';
        var eventAt = eventData ? (eventData.event_at || '—') : '—';
        var venue = eventData ? (eventData.location || '') : '';
        setPreviewBackground(eventData && eventData.template ? eventData.template.background : '');

        var catText = $('#category_id option:selected').text() || '';
        var inviteType = (catText && catText !== 'Select Invitation Type') ? catText : 'Guest';
        $('#oi-preview-title').text(inviteType);
        $('#oi-preview-message').text('You are cordially invited as ' + inviteType + ' to');
        $('#oi-preview-event').text(eventName);
        $('#oi-preview-date').text(eventAt);
        $('#oi-preview-venue').text(venue || '—');
        $('#oi-preview-venue-row').toggle(!!venue);

        var mode = $('#recipient_mode').val();
        var recipientText = '—';
        var dearName = 'Guest';
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
                }
            }
        } else if (mode === 'csv') {
            var fileInput = document.querySelector('input[name="recipient_csv"]');
            recipientText = (fileInput && fileInput.files && fileInput.files.length) ? ('CSV: ' + fileInput.files[0].name) : '—';
            dearName = 'Guest';
        }
        $('#oi-preview-recipients').text(recipientText);
        $('#oi-preview-dear').text('Dear ' + dearName + ',');

        var rsvp = ($('#rsvp').val() || '').toString().trim();
        var rsvpDisplay = rsvp || (oiBaseUrl + '/online-invitation/invite/xxxxx');
        $('#oi-preview-rsvp-label').text(rsvp ? 'RSVP' : 'RSVP / View');
        $('#oi-preview-rsvp').text(rsvpDisplay);

        var borderColor = syncColorPair('border_color', 'border_color_picker', '#c8a75e');
        $('#oi-preview-border-outer').css('border-color', borderColor);
        $('#oi-preview-border-inner').css('border-color', borderColor);
        $('#oi-preview-qr-box').css('border-color', borderColor);
        $('#oi-preview-details').css('border-color', borderColor);
        $('#oi-preview-event').css('border-bottom-color', borderColor);
        $('#oi-preview-footer').css('border-top-color', borderColor);
        $('#oi-preview-venue-row').css('border-top-color', borderColor);

        var fontColor = syncColorPair('font_color', 'font_color_picker', '#f3e7c1');
        $('#oi-preview-canvas').css('color', fontColor);
        $('#oi-preview-text').find('.oi-pv').css('color', fontColor);

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
    bindColorPair('border_color', 'border_color_picker', '#c8a75e');
    bindColorPair('font_color', 'font_color_picker', '#f3e7c1');

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

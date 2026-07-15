@extends('layout.main')

@section('content')
@php
    $anTab = 'announcements.compose';
    $clone = $clone ?? null;
    $defaultHeader = old('header', $clone['header'] ?? ($settings->default_header ?? 'Beyond Enterprise'));
@endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap" style="gap:12px;">
            <div>
                <h1 class="an-title"><i class="fa fa-bullhorn"></i> Compose Announcement</h1>
                <p class="an-subtitle">Create and send WhatsApp messages to customers and staff. No action required from recipients.</p>
            </div>
            <div class="an-page-card py-2 px-3 mb-0">
                <small class="text-muted d-block">Selected Recipients</small>
                <strong id="an-count" style="color:#0b3f90;font-size:1.25rem;">0</strong>
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

<style>
    .an-layout { display: grid; grid-template-columns: 1fr 320px; gap: 16px; }
    @media (max-width: 992px) { .an-layout { grid-template-columns: 1fr; } }
    .an-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
    .an-field { width:100%; border:1px solid #d7deea; border-radius:8px; padding:9px 12px; font-size:14px; }
    .an-field:focus { outline:none; border-color:#0b3f90; box-shadow:0 0 0 3px rgba(11,63,144,.12); }
    textarea.an-field { min-height:140px; resize:vertical; }
    .an-ph {
        display:inline-block; border:1px solid #9bb6e0; color:#0b3f90; border-radius:999px;
        padding:2px 10px; font-size:12px; margin:2px 2px 0 0; cursor:pointer; background:#f0f6ff; font-weight:600;
    }
    .an-pill {
        border:0; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600;
        background:#f1f5f9; color:#334155; cursor:pointer; margin:0 4px 6px 0;
    }
    .an-pill.active { background:#0b3f90; color:#fff; }
    .an-user-list { max-height:220px; overflow:auto; border:1px solid #e3e9f4; border-radius:10px; background:#fff; }
    .an-user-item { display:block; width:100%; text-align:left; padding:10px 12px; border:0; border-bottom:1px solid #f0f3f8; background:#fff; cursor:pointer; }
    .an-user-item:hover, .an-user-item.selected { background:#f0f6ff; }
    .an-user-item .meta { color:#6b7280; font-size:12px; }
    .an-chip {
        display:inline-flex; align-items:center; gap:6px; border:1px solid #0b3f90; color:#0b3f90;
        background:#eef4ff; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:600; margin:2px;
    }
    .an-chip button { border:0; background:transparent; color:#0b3f90; font-weight:800; cursor:pointer; }
    .an-send-opt {
        border:1px solid #d7deea; border-radius:10px; padding:10px 12px; cursor:pointer;
        font-size:13px; font-weight:600; margin-bottom:8px; background:#fff;
    }
    .an-send-opt.active { border-color:#0b3f90; background:#eef4ff; color:#0b3f90; }
    .an-info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; border-radius:8px; padding:8px 10px; font-size:12px; margin-top:8px; }
    .an-drop {
        border:1px dashed #94a3b8; border-radius:10px; padding:16px; text-align:center; color:#64748b; font-size:13px;
    }
</style>

        <form method="POST" action="{{ route('announcements.store') }}" enctype="multipart/form-data" id="an-form">
            @csrf
            <input type="hidden" name="cloned_from_id" value="{{ $clone['cloned_from_id'] ?? '' }}">
            <input type="hidden" name="send_mode" id="an-send-mode" value="now">
            <input type="hidden" name="send_whatsapp" value="1">

            <div class="an-layout">
                <div class="an-page-card">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
                        <h5 class="mb-0" style="color:#0b3f90;font-weight:700;">Message Content</h5>
                        <div>
                            <label class="an-label mb-0">Template</label>
                            <select class="an-field" id="an-template-pick" style="min-width:180px;">
                                <option value="">Blank Message</option>
                                @foreach($templates as $tpl)
                                    <option value="{{ $tpl->id }}"
                                        data-subject="{{ e($tpl->subject) }}"
                                        data-header="{{ e($tpl->header) }}"
                                        data-body="{{ e($tpl->body) }}"
                                        data-category="{{ $tpl->category_id }}">{{ $tpl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="an-label">Category</label>
                            <select name="category_id" class="an-field" id="an-category">
                                <option value="">General</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string)old('category_id', $clone['category_id'] ?? '') === (string)$cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8 form-group">
                            <label class="an-label">Subject</label>
                            <input type="text" name="subject" class="an-field" required placeholder="Internal tracking title" value="{{ old('subject', $clone['subject'] ?? '') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="an-label">Header (WhatsApp opening line)</label>
                        <input type="text" name="header" class="an-field" value="{{ $defaultHeader }}">
                    </div>

                    <div class="form-group">
                        <label class="an-label">Message Body</label>
                        <div class="mb-2" style="font-size:12px;color:#9ca3af;">
                            Insert:
                            @foreach(['{name}','{email}','{phone}','{address}','{date}','{institution_name}','{reference}'] as $tok)
                                <span class="an-ph" data-token="{{ $tok }}">{{ $tok }}</span>
                            @endforeach
                        </div>
                        <textarea name="body" id="an-body" class="an-field" rows="8" required placeholder="Dear {name},">{{ old('body', $clone['body'] ?? "Dear {name},\n\n") }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="an-label">Footer (optional)</label>
                        <input type="text" name="footer" class="an-field" value="{{ old('footer', $clone['footer'] ?? '') }}" placeholder="Beyond Enterprise">
                    </div>

                    <div class="an-drop">
                        <div class="mb-2">Attachments (max 10MB)</div>
                        <input type="file" name="attachment" accept=".pdf,image/*" class="an-field">
                    </div>
                </div>

                <div>
                    <div class="an-page-card">
                        <h5 style="color:#0b3f90;font-weight:700;">Sending Options</h5>
                        <label class="d-flex align-items-center" style="gap:8px;font-weight:600;">
                            <input type="checkbox" checked disabled> Send via WhatsApp
                        </label>
                        <div class="an-info">Messages are sent one recipient every 6 seconds. No accept/reject action is required.</div>
                        <div class="mt-3">
                            <div class="an-send-opt active" data-mode="now">✈ Send immediately</div>
                            <div class="an-send-opt" data-mode="schedule"><i class="dripicons-clock"></i> Schedule for later</div>
                            <input type="datetime-local" name="schedule_at" id="an-schedule-at" class="an-field d-none mt-2">
                        </div>
                        <div class="mt-3">
                            <label class="an-label">Reminders</label>
                            <div id="an-reminders"></div>
                            <button type="button" class="an-btn-outline mt-1" id="an-add-reminder">+ Add reminder</button>
                        </div>
                        <div class="mt-3">
                            <label class="d-flex align-items-center" style="gap:8px;">
                                <input type="checkbox" name="save_as_template" value="1"> Save as template
                            </label>
                            <input type="text" name="template_name" class="an-field mt-1" placeholder="Template name (optional)">
                        </div>
                        <div class="mt-3 d-flex flex-column" style="gap:8px;">
                            <button type="submit" class="an-btn-primary" style="justify-content:center;padding:12px;"><i class="dripicons-rocket"></i> Send Now</button>
                        </div>
                    </div>

                    <div class="an-page-card">
                        <h5 style="color:#0b3f90;font-weight:700;">Select Recipients *</h5>
                        <div class="mb-2">
                            <button type="button" class="an-pill active an-rf" data-role="customers">Customers</button>
                            <button type="button" class="an-pill an-rf" data-role="staff">System Users</button>
                            <button type="button" class="an-pill an-rf" data-role="all">All</button>
                        </div>
                        <div class="d-flex mb-2" style="gap:8px;">
                            <input type="search" class="an-field an-rsearch" placeholder="Search name, email, phone…">
                            <button type="button" class="an-btn-outline an-rselect-all" style="white-space:nowrap;">Select all</button>
                        </div>
                        <div class="an-user-list an-rlist"></div>
                        <div class="an-rchips mt-2"></div>
                        <div class="an-rhiddens"></div>

                        <hr>
                        <h6 style="font-weight:700;">CC (no action required)</h6>
                        <p class="text-muted small">Copied recipients also receive the WhatsApp message.</p>
                        <div class="mb-2">
                            <button type="button" class="an-pill active an-cf" data-role="all">All</button>
                            <button type="button" class="an-pill an-cf" data-role="staff">Staff</button>
                            <button type="button" class="an-pill an-cf" data-role="customers">Customers</button>
                        </div>
                        <input type="search" class="an-field an-csearch mb-2" placeholder="Search CC…">
                        <div class="an-user-list an-clist" style="max-height:140px;"></div>
                        <div class="an-cchips mt-2"></div>
                        <div class="an-chiddens"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
window.AN_USERS = @json($users);
window.AN_PRESELECT = @json([
    'recipients' => $clone['recipient_ids'] ?? [],
    'cc' => $clone['cc_ids'] ?? [],
]);
(function () {
    var recipients = (window.AN_PRESELECT.recipients || []).slice();
    var ccs = (window.AN_PRESELECT.cc || []).slice();
    var rRole = 'customers', cRole = 'all';

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function filterUsers(query, roleFilter) {
        var q = (query || '').toLowerCase();
        return (window.AN_USERS || []).filter(function (u) {
            var role = (u.role || '').toLowerCase();
            var source = (u.source || '').toLowerCase();
            if (roleFilter === 'staff' && (source === 'customer' || role === 'customer' || role === 'client')) return false;
            if (roleFilter === 'customers' && source !== 'customer' && role !== 'customer' && role !== 'client') return false;
            if (!q) return true;
            return (u.name||'').toLowerCase().indexOf(q) !== -1
                || (u.email||'').toLowerCase().indexOf(q) !== -1
                || (u.phone||'').toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderList(el, users, selected, onToggle) {
        el.innerHTML = users.map(function (u) {
            var sel = selected.indexOf(u.id) !== -1 ? ' selected' : '';
            return '<button type="button" class="an-user-item'+sel+'" data-id="'+esc(u.id)+'">'
                + '<div class="font-weight-bold">'+esc(u.name||'Untitled')+'</div>'
                + '<div class="meta">'+esc(u.email||'')+' · '+esc(u.phone||'')+'</div>'
                + '</button>';
        }).join('') || '<div class="p-3 text-muted small text-center">No people found.</div>';
        el.querySelectorAll('.an-user-item').forEach(function (item) {
            item.addEventListener('click', function () { onToggle(item.getAttribute('data-id')); });
        });
    }

    function renderChips(el, selected, onRemove, prefix) {
        var map = {};
        (window.AN_USERS || []).forEach(function (u) { map[u.id] = u; });
        el.innerHTML = selected.map(function (id) {
            var u = map[id] || { name: id };
            return '<span class="an-chip" data-id="'+esc(id)+'">'+esc((prefix?prefix+' ':'')+(u.name||id))
                + ' <button type="button">×</button></span>';
        }).join('');
        el.querySelectorAll('.an-chip button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                onRemove(btn.parentNode.getAttribute('data-id'));
            });
        });
    }

    function syncHiddens(el, selected, name) {
        el.innerHTML = selected.map(function (id) {
            return '<input type="hidden" name="'+name+'[]" value="'+esc(id)+'">';
        }).join('');
        document.getElementById('an-count').textContent = String(recipients.length + ccs.length);
    }

    function refreshRecipients() {
        renderList(document.querySelector('.an-rlist'), filterUsers(document.querySelector('.an-rsearch').value, rRole), recipients, function (id) {
            var i = recipients.indexOf(id);
            if (i === -1) recipients.push(id); else recipients.splice(i, 1);
            refreshRecipients();
        });
        renderChips(document.querySelector('.an-rchips'), recipients, function (id) {
            recipients = recipients.filter(function (x) { return x !== id; });
            refreshRecipients();
        });
        syncHiddens(document.querySelector('.an-rhiddens'), recipients, 'recipient_ids');
    }

    function refreshCc() {
        renderList(document.querySelector('.an-clist'), filterUsers(document.querySelector('.an-csearch').value, cRole), ccs, function (id) {
            var i = ccs.indexOf(id);
            if (i === -1) ccs.push(id); else ccs.splice(i, 1);
            refreshCc();
        });
        renderChips(document.querySelector('.an-cchips'), ccs, function (id) {
            ccs = ccs.filter(function (x) { return x !== id; });
            refreshCc();
        }, 'CC:');
        syncHiddens(document.querySelector('.an-chiddens'), ccs, 'cc_ids');
    }

    document.querySelector('.an-rsearch').addEventListener('input', refreshRecipients);
    document.querySelector('.an-csearch').addEventListener('input', refreshCc);
    document.querySelectorAll('.an-rf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            rRole = btn.getAttribute('data-role');
            document.querySelectorAll('.an-rf').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshRecipients();
        });
    });
    document.querySelectorAll('.an-cf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            cRole = btn.getAttribute('data-role');
            document.querySelectorAll('.an-cf').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshCc();
        });
    });
    document.querySelector('.an-rselect-all').addEventListener('click', function () {
        filterUsers(document.querySelector('.an-rsearch').value, rRole).forEach(function (u) {
            if (recipients.indexOf(u.id) === -1) recipients.push(u.id);
        });
        refreshRecipients();
    });

    document.querySelectorAll('.an-ph').forEach(function (ph) {
        ph.addEventListener('click', function () {
            var ta = document.getElementById('an-body');
            ta.value = (ta.value || '') + ph.getAttribute('data-token');
            ta.focus();
        });
    });

    document.querySelectorAll('.an-send-opt').forEach(function (opt) {
        opt.addEventListener('click', function () {
            document.querySelectorAll('.an-send-opt').forEach(function (o) { o.classList.remove('active'); });
            opt.classList.add('active');
            var mode = opt.getAttribute('data-mode');
            document.getElementById('an-send-mode').value = mode;
            document.getElementById('an-schedule-at').classList.toggle('d-none', mode !== 'schedule');
            var submit = document.querySelector('#an-form button[type=submit]');
            submit.innerHTML = mode === 'schedule'
                ? '<i class="dripicons-clock"></i> Schedule'
                : '<i class="dripicons-rocket"></i> Send Now';
        });
    });

    document.getElementById('an-add-reminder').addEventListener('click', function () {
        var box = document.getElementById('an-reminders');
        var row = document.createElement('div');
        row.className = 'd-flex mb-2';
        row.style.gap = '8px';
        row.innerHTML = '<input type="datetime-local" name="reminders[]" class="an-field">'
            + '<button type="button" class="an-btn-outline text-danger">×</button>';
        row.querySelector('button').addEventListener('click', function () { row.remove(); });
        box.appendChild(row);
    });

    document.getElementById('an-template-pick').addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) return;
        document.querySelector('[name=subject]').value = opt.getAttribute('data-subject') || '';
        document.querySelector('[name=header]').value = opt.getAttribute('data-header') || '';
        document.getElementById('an-body').value = opt.getAttribute('data-body') || '';
        var cat = opt.getAttribute('data-category');
        if (cat) document.getElementById('an-category').value = cat;
    });

    document.getElementById('an-form').addEventListener('submit', function (e) {
        if (!recipients.length) {
            e.preventDefault();
            alert('Select at least one recipient.');
        }
    });

    refreshRecipients();
    refreshCc();
})();
</script>
@endsection

@extends('layout.main')

@section('content')
@php
    $tmTab = 'tasks.create';
    $usersJson = collect($users)->map(function ($u) {
        if (is_array($u)) {
            return $u;
        }
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'address' => $u->address ?? '',
            'role' => $u->role ?? '',
            'source' => $u->source ?? 'Portal',
        ];
    })->values();
@endphp
<section class="forms">
    <div class="container-fluid tm-shell" style="max-width:900px;">
        @include('task_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="tm-title">Create Task</h1>
            <p class="tm-subtitle">Each task can have its own color, period, assignees, PDF, and schedule. Timezone: Africa/Kigali.</p>
        </div>

        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

<style>
    .tm-create-card {
        background: #fff; border: 1px solid #e8eef6; border-radius: 16px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1.25rem 1.35rem 1.5rem;
        margin-bottom: 1rem;
    }
    .tm-create-card > h2 { color: #0b3f90; font-size: 1.15rem; font-weight: 700; margin: 0 0 4px; }
    .tm-create-card > .tm-card-desc { color: #6b7280; font-size: 13px; margin: 0 0 1rem; }
    .tm-task-card {
        border: 2px solid #0b3f90; border-radius: 14px; background: #fff;
        overflow: hidden; margin-bottom: 1rem;
    }
    .tm-task-bar { height: 6px; background: #0b3f90; }
    .tm-task-body { padding: 1.1rem 1.15rem 1.25rem; }
    .tm-task-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1rem;
    }
    .tm-task-head .tm-task-label { font-weight: 700; font-size: 14px; color: #0b3f90; }
    .tm-task-remove {
        border: 0; background: transparent; color: #e11d48; font-size: 13px;
        font-weight: 600; cursor: pointer; padding: 4px 8px;
    }
    .tm-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .tm-label .req { color: #dc2626; }
    .tm-field {
        width: 100%; border: 1px solid #d7deea; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; background: #fff;
    }
    .tm-field:focus { outline: none; border-color: #0b3f90; box-shadow: 0 0 0 3px rgba(11,63,144,.12); }
    textarea.tm-field { min-height: 88px; resize: vertical; }
    .tm-main-grid {
        display: grid; grid-template-columns: 1fr 168px; gap: 1rem; margin-bottom: 1rem;
    }
    @media (max-width: 768px) { .tm-main-grid { grid-template-columns: 1fr; } }
    .tm-priority-box {
        border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; padding: 4px;
    }
    .tm-priority-box button {
        display: block; width: 100%; border: 0; background: transparent;
        text-align: left; padding: 8px 12px; border-radius: 7px;
        font-size: 13px; font-weight: 500; color: #374151; cursor: pointer;
    }
    .tm-priority-box button.active { background: #0b3f90; color: #fff; }
    .tm-ph {
        display: inline-block; border: 1px solid #9bb6e0; color: #0b3f90; border-radius: 999px;
        padding: 2px 10px; font-size: 12px; margin: 2px 2px 0 0; cursor: pointer; background: #f0f6ff;
        font-weight: 600;
    }
    .tm-ph:hover { background: #e0ecff; }
    .tm-color-dot {
        width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent;
        cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        margin-right: 6px; color: #fff; font-size: 14px; font-weight: 700; vertical-align: middle;
    }
    .tm-color-dot.active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #94a3b8; }
    .tm-dates {
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
        border-top: 1px solid #eef2f7; padding-top: 14px; margin-top: 4px;
    }
    @media (max-width: 768px) { .tm-dates { grid-template-columns: 1fr 1fr; } }
    .tm-section { border-top: 1px solid #eef2f7; padding-top: 14px; margin-top: 14px; }
    .tm-section-title { font-size: 14px; font-weight: 700; color: #111827; margin: 0 0 8px; }
    .tm-pill {
        border: 0; border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 600;
        background: #f1f5f9; color: #334155; cursor: pointer; margin: 0 4px 6px 0;
    }
    .tm-pill.active { background: #0b3f90; color: #fff; }
    .tm-pill-outline {
        border: 1px solid #cbd5e1; border-radius: 8px; padding: 5px 10px; font-size: 12px;
        font-weight: 600; background: #fff; color: #334155; cursor: pointer;
    }
    .tm-browse-pdf {
        display: inline-flex; align-items: center; gap: 6px;
        border: 1px solid #cbd5e1; background: #fff; border-radius: 8px;
        padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; color: #334155;
    }
    .tm-browse-pdf:hover { background: #f8fafc; }
    .tm-pdf-name { font-size: 13px; color: #475569; margin-left: 8px; }
    .tm-user-list {
        max-height: 180px; overflow: auto; border: 1px solid #e3e9f4; border-radius: 10px; background: #fff;
    }
    .tm-user-item { padding: 10px 12px; border-bottom: 1px solid #f0f3f8; cursor: pointer; text-align: left; width: 100%; background: #fff; border-left: 0; border-right: 0; border-top: 0; display: block; }
    .tm-user-item:last-child { border-bottom: 0; }
    .tm-user-item:hover, .tm-user-item.selected { background: #f0f6ff; }
    .tm-user-item .meta { color: #6b7280; font-size: 12px; }
    .tm-chip {
        display: inline-flex; align-items: center; gap: 6px;
        border: 1px solid #0b3f90; color: #0b3f90; background: #eef4ff;
        border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; margin: 2px;
    }
    .tm-chip button {
        border: 0; background: transparent; color: #0b3f90; font-weight: 800; line-height: 1; cursor: pointer; padding: 0 2px;
    }
    .tm-send-opt {
        border: 1px solid #d7deea; border-radius: 10px; padding: 10px 14px; cursor: pointer; flex: 1;
        font-size: 13px; font-weight: 600; color: #334155; background: #fff;
        display: flex; align-items: center; gap: 8px;
    }
    .tm-send-opt.active { border-color: #0b3f90; background: #eef4ff; color: #0b3f90; }
    .tm-add-another {
        width: 100%; border: 1px dashed #94a3b8; background: #fff; border-radius: 10px;
        padding: 12px; font-weight: 600; color: #475569; cursor: pointer; margin-top: 4px;
    }
    .tm-add-another:hover { background: #f8fafc; color: #0b3f90; border-color: #0b3f90; }
    .tm-actions {
        display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;
        position: sticky; bottom: 12px; background: rgba(248,250,252,.95);
        border-top: 1px solid #e5e7eb; padding: 12px 0; margin-top: 8px; z-index: 5;
    }
    .tm-btn-cancel {
        border: 1px solid #0b3f90; background: #fff; color: #0b3f90;
        border-radius: 8px; padding: 10px 18px; font-weight: 600; font-size: 14px;
        text-decoration: none; display: inline-flex; align-items: center;
    }
    .tm-btn-cancel:hover { background: #f0f6ff; color: #0b3f90; text-decoration: none; }
    .tm-btn-send {
        border: 0; background: #0b3f90; color: #fff; border-radius: 8px;
        padding: 10px 22px; font-weight: 700; font-size: 14px; min-width: 200px;
        display: inline-flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;
    }
    .tm-btn-send:hover { background: #0a3578; }
    .tm-hint { color: #6b7280; font-size: 12px; margin: 0 0 8px; }
    .tm-search-wrap { position: relative; flex: 1; }
    .tm-search-wrap .tm-field { padding-left: 34px; }
    .tm-search-wrap:before {
        content: "⌕"; position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: 14px; pointer-events: none;
    }
</style>

        <form method="POST" action="{{ route('tasks.store') }}" enctype="multipart/form-data" id="tm-create-form">
            @csrf
            <div class="tm-create-card">
                <h2>Tasks</h2>
                <p class="tm-card-desc">Configure each task independently, then send all together.</p>
                <div id="tm-tasks"></div>
                <button type="button" class="tm-add-another" id="tm-add-task">+ Add Another Task</button>
            </div>

            <div class="tm-actions">
                <a href="{{ route('tasks.dashboard') }}" class="tm-btn-cancel">Cancel</a>
                <button type="submit" class="tm-btn-send"><i class="dripicons-rocket"></i> Send All Tasks</button>
            </div>
        </form>
    </div>
</section>

<script>
window.TM_USERS = @json($usersJson);
(function () {
    var container = document.getElementById('tm-tasks');
    var taskIndex = 0;
    var colors = ['#0b3f90', '#16a34a', '#ea580c', '#dc2626', '#7c3aed', '#0d9488'];

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function todayParts() {
        var d = new Date();
        return {
            date: d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()),
            time: pad(d.getHours()) + ':' + pad(d.getMinutes())
        };
    }

    function filterUsers(query, roleFilter) {
        var q = (query || '').toLowerCase();
        return (window.TM_USERS || []).filter(function (u) {
            var role = (u.role || '').toLowerCase();
            var source = (u.source || '').toLowerCase();
            if (roleFilter === 'staff' && role === 'customer' && source !== 'user' && source !== 'portal') return false;
            if (roleFilter === 'staff' && source === 'customer') return false;
            if (roleFilter === 'customers' && source !== 'customer' && role !== 'customer' && role !== 'client') return false;
            if (!q) return true;
            return (u.name||'').toLowerCase().indexOf(q) !== -1
                || (u.email||'').toLowerCase().indexOf(q) !== -1
                || (u.phone||'').toLowerCase().indexOf(q) !== -1
                || (u.address||'').toLowerCase().indexOf(q) !== -1
                || (u.source||'').toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderUserList(el, users, selectedIds, onToggle) {
        el.innerHTML = users.map(function (u) {
            var sel = selectedIds.indexOf(u.id) !== -1 ? ' selected' : '';
            return '<button type="button" class="tm-user-item'+sel+'" data-id="'+esc(u.id)+'">'
                + '<div class="font-weight-bold">'+esc(u.name || 'Untitled')
                + (u.source ? ' <span class="badge badge-light">'+esc(u.source)+'</span>' : '')
                + '</div>'
                + '<div class="meta">'+esc(u.email || '')+' · '+esc(u.phone || '')+'</div>'
                + '</button>';
        }).join('') || '<div class="p-3 text-muted small text-center">No members found.</div>';
        el.querySelectorAll('.tm-user-item').forEach(function (item) {
            item.addEventListener('click', function () { onToggle(item.getAttribute('data-id')); });
        });
    }

    function renderChips(el, selectedIds, onRemove, prefix) {
        var map = {};
        (window.TM_USERS || []).forEach(function (u) { map[u.id] = u; });
        el.innerHTML = selectedIds.map(function (id) {
            var u = map[id] || { name: id };
            var label = (prefix ? prefix + ' ' : '') + (u.name || id);
            return '<span class="tm-chip" data-id="'+esc(id)+'">'+esc(label)
                + ' <button type="button" title="Remove" aria-label="Remove">×</button></span>';
        }).join('');
        el.querySelectorAll('.tm-chip button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                onRemove(btn.parentNode.getAttribute('data-id'));
            });
        });
    }

    function setTaskColor(wrap, hex) {
        wrap.style.borderColor = hex;
        var bar = wrap.querySelector('.tm-task-bar');
        var label = wrap.querySelector('.tm-task-label');
        if (bar) bar.style.background = hex;
        if (label) label.style.color = hex;
        wrap.querySelector('.tm-color-input').value = hex;
        wrap.querySelectorAll('.tm-color-dot').forEach(function (d) {
            var on = d.getAttribute('data-color') === hex;
            d.classList.toggle('active', on);
            d.innerHTML = on ? '✓' : '';
        });
    }

    function renumberTasks() {
        var cards = container.querySelectorAll('.tm-task-card');
        cards.forEach(function (card, idx) {
            var label = card.querySelector('.tm-task-label');
            if (label) label.textContent = 'Task ' + (idx + 1);
            var remove = card.querySelector('.tm-task-remove');
            if (remove) remove.style.display = cards.length > 1 ? '' : 'none';
        });
    }

    function addTaskCard() {
        var i = taskIndex++;
        var assignees = [];
        var ccs = [];
        var now = todayParts();
        var color = colors[i % colors.length];
        var wrap = document.createElement('div');
        wrap.className = 'tm-task-card';
        wrap.dataset.index = String(i);
        wrap.style.borderColor = color;
        wrap.innerHTML = ''
            + '<div class="tm-task-bar" style="background:'+color+'"></div>'
            + '<div class="tm-task-body">'
            + '  <div class="tm-task-head">'
            + '    <span class="tm-task-label" style="color:'+color+'">Task '+(i+1)+'</span>'
            + '    <button type="button" class="tm-task-remove" style="display:none;">Remove</button>'
            + '  </div>'
            + '  <div class="tm-main-grid">'
            + '    <div>'
            + '      <div class="mb-3">'
            + '        <label class="tm-label">Subject <span class="req">*</span></label>'
            + '        <input type="text" name="tasks['+i+'][subject]" class="tm-field tm-subject" placeholder="Task subject" required>'
            + '      </div>'
            + '      <div>'
            + '        <label class="tm-label">Description</label>'
            + '        <textarea name="tasks['+i+'][description]" class="tm-field tm-desc" rows="3" placeholder="Describe the task…"></textarea>'
            + '        <div class="mt-2" style="font-size:12px;color:#9ca3af;">Insert: '
            + '          <span class="tm-ph" data-token="{Name}">{Name}</span>'
            + '          <span class="tm-ph" data-token="{Phone}">{Phone}</span>'
            + '          <span class="tm-ph" data-token="{Email}">{Email}</span>'
            + '          <span class="tm-ph" data-token="{Address}">{Address}</span>'
            + '        </div>'
            + '      </div>'
            + '    </div>'
            + '    <div>'
            + '      <label class="tm-label">Priority</label>'
            + '      <div class="tm-priority-box" data-priority>'
            + '        <button type="button" data-val="Low">Low</button>'
            + '        <button type="button" class="active" data-val="Medium">Medium</button>'
            + '        <button type="button" data-val="High">High</button>'
            + '        <button type="button" data-val="Emergency">Emergency</button>'
            + '      </div>'
            + '      <input type="hidden" name="tasks['+i+'][priority]" value="Medium" class="tm-priority-input">'
            + '    </div>'
            + '  </div>'
            + '  <div class="mb-3">'
            + '    <label class="tm-label">Task Color</label>'
            + '    <div class="tm-colors">'
            + colors.map(function (c) {
                return '<span class="tm-color-dot'+(c===color?' active':'')+'" data-color="'+c+'" style="background:'+c+'">'+(c===color?'✓':'')+'</span>';
            }).join('')
            + '    </div>'
            + '    <input type="hidden" name="tasks['+i+'][color]" value="'+color+'" class="tm-color-input">'
            + '  </div>'
            + '  <div class="tm-dates">'
            + '    <div><label class="tm-label">Start Date</label><input type="date" name="tasks['+i+'][start_date]" class="tm-field" value="'+now.date+'"></div>'
            + '    <div><label class="tm-label">Start Time</label><input type="time" name="tasks['+i+'][start_time]" class="tm-field" value="'+now.time+'"></div>'
            + '    <div><label class="tm-label">End Date <span class="req">*</span></label><input type="date" name="tasks['+i+'][end_date]" class="tm-field" value="'+now.date+'" required></div>'
            + '    <div><label class="tm-label">End Time</label><input type="time" name="tasks['+i+'][end_time]" class="tm-field" value="'+now.time+'"></div>'
            + '  </div>'
            + '  <div class="tm-section">'
            + '    <label class="tm-label">PDF (optional)</label>'
            + '    <input type="file" name="tasks['+i+'][pdf]" class="tm-pdf-input d-none" accept="application/pdf">'
            + '    <button type="button" class="tm-browse-pdf"><i class="dripicons-document"></i> Browse PDF</button>'
            + '    <span class="tm-pdf-name">No file selected</span>'
            + '  </div>'
            + '  <div class="tm-section">'
            + '    <div class="tm-section-title">Assign To <span class="req">*</span></div>'
            + '    <div class="mb-2">'
            + '      <button type="button" class="tm-pill active tm-af" data-role="all">All Members</button>'
            + '      <button type="button" class="tm-pill tm-af" data-role="staff">Staff</button>'
            + '      <button type="button" class="tm-pill tm-af" data-role="customers">Customers</button>'
            + '    </div>'
            + '    <div class="d-flex mb-2" style="gap:8px;">'
            + '      <div class="tm-search-wrap"><input type="search" class="tm-field tm-asearch" placeholder="Search…"></div>'
            + '      <button type="button" class="tm-pill-outline tm-aselect-all">Select everyone</button>'
            + '    </div>'
            + '    <div class="tm-user-list tm-alist"></div>'
            + '    <div class="tm-achips mt-2"></div>'
            + '    <div class="tm-ahiddens"></div>'
            + '    <small class="text-danger tm-aerr d-none">Pick at least one assignee.</small>'
            + '  </div>'
            + '  <div class="tm-section">'
            + '    <div class="tm-section-title">CC (Carbon Copy)</div>'
            + '    <p class="tm-hint">Teachers or supervisors who should follow progress (not assignees).</p>'
            + '    <div class="mb-2">'
            + '      <button type="button" class="tm-pill active tm-cf" data-role="all">All Members</button>'
            + '      <button type="button" class="tm-pill tm-cf" data-role="staff">Staff</button>'
            + '      <button type="button" class="tm-pill tm-cf" data-role="customers">Customers</button>'
            + '    </div>'
            + '    <div class="d-flex mb-2" style="gap:8px;">'
            + '      <div class="tm-search-wrap"><input type="search" class="tm-field tm-csearch" placeholder="Search CC recipients…"></div>'
            + '      <button type="button" class="tm-pill-outline tm-cselect-all">Select everyone</button>'
            + '    </div>'
            + '    <div class="tm-user-list tm-clist"></div>'
            + '    <div class="tm-cchips mt-2"></div>'
            + '    <div class="tm-chiddens"></div>'
            + '  </div>'
            + '  <div class="tm-section">'
            + '    <div class="tm-section-title"><i class="dripicons-clock"></i> Reminders</div>'
            + '    <p class="tm-hint">Multiple reminders before deadline — message shows time remaining.</p>'
            + '    <div class="tm-reminders"></div>'
            + '    <button type="button" class="tm-pill-outline tm-add-reminder">+ Add reminder</button>'
            + '  </div>'
            + '  <div class="tm-section">'
            + '    <div class="tm-section-title"><i class="dripicons-clock"></i> When to Send</div>'
            + '    <div class="d-flex" style="gap:10px;flex-wrap:wrap;">'
            + '      <div class="tm-send-opt active" data-mode="now">✈ Send immediately</div>'
            + '      <div class="tm-send-opt" data-mode="schedule">📅 Schedule</div>'
            + '    </div>'
            + '    <input type="hidden" name="tasks['+i+'][send_mode]" value="now" class="tm-send-mode">'
            + '    <input type="datetime-local" name="tasks['+i+'][schedule_at]" class="tm-field mt-2 tm-schedule-at d-none" style="max-width:280px;">'
            + '  </div>'
            + '</div>';

        container.appendChild(wrap);

        var aRole = 'all', cRole = 'all';
        var aList = wrap.querySelector('.tm-alist');
        var cList = wrap.querySelector('.tm-clist');
        var aChips = wrap.querySelector('.tm-achips');
        var cChips = wrap.querySelector('.tm-cchips');
        var aHiddens = wrap.querySelector('.tm-ahiddens');
        var cHiddens = wrap.querySelector('.tm-chiddens');
        var aErr = wrap.querySelector('.tm-aerr');

        function syncAssigneeHiddens() {
            aHiddens.innerHTML = assignees.map(function (id) {
                return '<input type="hidden" name="tasks['+i+'][assignee_ids][]" value="'+esc(id)+'">';
            }).join('');
            aErr.classList.toggle('d-none', assignees.length > 0);
        }
        function syncCcHiddens() {
            cHiddens.innerHTML = ccs.map(function (id) {
                return '<input type="hidden" name="tasks['+i+'][cc_ids][]" value="'+esc(id)+'">';
            }).join('');
        }
        function refreshAssignees() {
            renderUserList(aList, filterUsers(wrap.querySelector('.tm-asearch').value, aRole), assignees, function (id) {
                var idx = assignees.indexOf(id);
                if (idx === -1) assignees.push(id); else assignees.splice(idx, 1);
                refreshAssignees();
            });
            renderChips(aChips, assignees, function (id) {
                assignees = assignees.filter(function (x) { return x !== id; });
                refreshAssignees();
            });
            syncAssigneeHiddens();
        }
        function refreshCc() {
            renderUserList(cList, filterUsers(wrap.querySelector('.tm-csearch').value, cRole), ccs, function (id) {
                var idx = ccs.indexOf(id);
                if (idx === -1) ccs.push(id); else ccs.splice(idx, 1);
                refreshCc();
            });
            renderChips(cChips, ccs, function (id) {
                ccs = ccs.filter(function (x) { return x !== id; });
                refreshCc();
            }, 'CC:');
            syncCcHiddens();
        }

        wrap.querySelector('.tm-task-remove').addEventListener('click', function () {
            wrap.remove();
            renumberTasks();
        });

        wrap.querySelector('.tm-asearch').addEventListener('input', refreshAssignees);
        wrap.querySelector('.tm-csearch').addEventListener('input', refreshCc);
        wrap.querySelectorAll('.tm-af').forEach(function (btn) {
            btn.addEventListener('click', function () {
                aRole = btn.getAttribute('data-role');
                wrap.querySelectorAll('.tm-af').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                refreshAssignees();
            });
        });
        wrap.querySelectorAll('.tm-cf').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cRole = btn.getAttribute('data-role');
                wrap.querySelectorAll('.tm-cf').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                refreshCc();
            });
        });
        wrap.querySelector('.tm-aselect-all').addEventListener('click', function () {
            filterUsers(wrap.querySelector('.tm-asearch').value, aRole).forEach(function (u) {
                if (assignees.indexOf(u.id) === -1) assignees.push(u.id);
            });
            refreshAssignees();
        });
        wrap.querySelector('.tm-cselect-all').addEventListener('click', function () {
            filterUsers(wrap.querySelector('.tm-csearch').value, cRole).forEach(function (u) {
                if (ccs.indexOf(u.id) === -1) ccs.push(u.id);
            });
            refreshCc();
        });

        wrap.querySelectorAll('[data-priority] button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                wrap.querySelectorAll('[data-priority] button').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                wrap.querySelector('.tm-priority-input').value = btn.getAttribute('data-val');
            });
        });
        wrap.querySelectorAll('.tm-color-dot').forEach(function (dot) {
            dot.addEventListener('click', function () {
                setTaskColor(wrap, dot.getAttribute('data-color'));
            });
        });
        wrap.querySelectorAll('.tm-ph').forEach(function (ph) {
            ph.addEventListener('click', function () {
                var ta = wrap.querySelector('.tm-desc');
                ta.value = (ta.value || '') + ph.getAttribute('data-token');
                ta.focus();
            });
        });
        wrap.querySelector('.tm-subject').addEventListener('blur', function () {
            var sub = wrap.querySelector('.tm-subject');
            sub.value = (sub.value || '').toUpperCase();
        });

        var pdfInput = wrap.querySelector('.tm-pdf-input');
        var pdfName = wrap.querySelector('.tm-pdf-name');
        wrap.querySelector('.tm-browse-pdf').addEventListener('click', function () { pdfInput.click(); });
        pdfInput.addEventListener('change', function () {
            pdfName.textContent = pdfInput.files && pdfInput.files[0] ? pdfInput.files[0].name : 'No file selected';
        });

        wrap.querySelector('.tm-add-reminder').addEventListener('click', function () {
            var box = wrap.querySelector('.tm-reminders');
            var row = document.createElement('div');
            row.className = 'd-flex mb-2';
            row.style.gap = '8px';
            row.style.alignItems = 'center';
            row.innerHTML = '<input type="datetime-local" name="tasks['+i+'][reminders][]" class="tm-field" style="max-width:280px;">'
                + '<button type="button" class="tm-task-remove" style="display:inline;">×</button>';
            row.querySelector('button').addEventListener('click', function () { row.remove(); });
            box.appendChild(row);
        });
        wrap.querySelectorAll('.tm-send-opt').forEach(function (opt) {
            opt.addEventListener('click', function () {
                wrap.querySelectorAll('.tm-send-opt').forEach(function (o) { o.classList.remove('active'); });
                opt.classList.add('active');
                var mode = opt.getAttribute('data-mode');
                wrap.querySelector('.tm-send-mode').value = mode;
                wrap.querySelector('.tm-schedule-at').classList.toggle('d-none', mode !== 'schedule');
            });
        });

        refreshAssignees();
        refreshCc();
        renumberTasks();
    }

    document.getElementById('tm-add-task').addEventListener('click', addTaskCard);
    document.getElementById('tm-create-form').addEventListener('submit', function (e) {
        var ok = true;
        container.querySelectorAll('.tm-task-card').forEach(function (card) {
            var hid = card.querySelectorAll('.tm-ahiddens input');
            var err = card.querySelector('.tm-aerr');
            if (!hid.length) {
                ok = false;
                if (err) err.classList.remove('d-none');
            }
            var sub = card.querySelector('.tm-subject');
            if (sub) sub.value = (sub.value || '').toUpperCase();
        });
        if (!ok) {
            e.preventDefault();
            alert('Each task needs at least one assignee.');
        }
    });

    addTaskCard();
})();
</script>
@endsection

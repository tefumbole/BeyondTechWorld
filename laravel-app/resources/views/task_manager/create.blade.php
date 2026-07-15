@extends('layout.main')

@section('content')
@php
    $tmTab = 'tasks.create';
    $usersJson = $users->map(function ($u) {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'address' => $u->address,
            'role' => $u->role,
        ];
    })->values();
@endphp
<style>
    .tm-chip {
        display: inline-flex; align-items: center; gap: 6px;
        border: 1px solid #0b3f90; color: #0b3f90; background: #eef4ff;
        border-radius: 999px; padding: 4px 10px; font-size: 13px; font-weight: 600; margin: 2px;
    }
    .tm-chip button {
        border: 0; background: transparent; color: #0b3f90; font-weight: 800; line-height: 1; cursor: pointer; padding: 0 2px;
    }
    .tm-user-list {
        max-height: 220px; overflow: auto; border: 1px solid #e3e9f4; border-radius: 10px; background: #fff;
    }
    .tm-user-item { padding: 10px 12px; border-bottom: 1px solid #f0f3f8; cursor: pointer; }
    .tm-user-item:hover, .tm-user-item.selected { background: #eef4ff; }
    .tm-user-item .meta { color: #6b7280; font-size: 12px; }
    .tm-priority-btn.active { background: #0b3f90; color: #fff; border-color: #0b3f90; }
    .tm-color-dot {
        width: 28px; height: 28px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; display: inline-block;
    }
    .tm-color-dot.active { box-shadow: 0 0 0 2px #0b3f90; }
    .tm-task-card { border: 1px solid #e3e9f4; border-radius: 14px; padding: 18px; margin-bottom: 16px; background: #fff; }
    .tm-ph {
        display: inline-block; border: 1px solid #9bb6e0; color: #0b3f90; border-radius: 8px;
        padding: 2px 8px; font-size: 12px; margin-right: 4px; cursor: pointer; background: #f5f9ff;
    }
    .tm-send-opt {
        border: 1px solid #d7deea; border-radius: 10px; padding: 12px; cursor: pointer; flex: 1;
    }
    .tm-send-opt.active { border-color: #0b3f90; background: #eef4ff; }
</style>

<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <h3 style="color:#0b3f90;">Create Task</h3>
        <p class="text-muted">Each task can have its own color, period, assignees, PDF, and schedule. Timezone: Africa/Kigali.</p>

        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        <form method="POST" action="{{ route('tasks.store') }}" enctype="multipart/form-data" id="tm-create-form">
            @csrf
            <div id="tm-tasks"></div>
            <button type="button" class="btn btn-light border w-100 mb-3" id="tm-add-task" style="border-style:dashed!important;">+ Add Another Task</button>
            <button type="submit" class="btn btn-primary btn-lg"><i class="dripicons-rocket"></i> Send Tasks</button>
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

    function filterUsers(query, roleFilter) {
        var q = (query || '').toLowerCase();
        return (window.TM_USERS || []).filter(function (u) {
            if (roleFilter === 'staff' && ['customer','client','student','applicant'].indexOf((u.role||'').toLowerCase()) !== -1) return false;
            if (roleFilter === 'customers' && ['customer','client','student','applicant'].indexOf((u.role||'').toLowerCase()) === -1) return false;
            if (!q) return true;
            return (u.name||'').toLowerCase().indexOf(q) !== -1
                || (u.email||'').toLowerCase().indexOf(q) !== -1
                || (u.phone||'').toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderUserList(el, users, selectedIds, onToggle) {
        el.innerHTML = users.map(function (u) {
            var sel = selectedIds.indexOf(u.id) !== -1 ? ' selected' : '';
            return '<div class="tm-user-item'+sel+'" data-id="'+esc(u.id)+'">'
                + '<div class="font-weight-bold">'+esc(u.name || 'Untitled')+'</div>'
                + '<div class="meta">'+esc(u.email || '')+' · '+esc(u.phone || '')+'</div>'
                + '</div>';
        }).join('') || '<div class="p-3 text-muted small">No members found.</div>';
        el.querySelectorAll('.tm-user-item').forEach(function (item) {
            item.addEventListener('click', function () { onToggle(item.getAttribute('data-id')); });
        });
    }

    function renderChips(el, selectedIds, onRemove) {
        var map = {};
        (window.TM_USERS || []).forEach(function (u) { map[u.id] = u; });
        el.innerHTML = selectedIds.map(function (id) {
            var u = map[id] || { name: id };
            return '<span class="tm-chip" data-id="'+esc(id)+'">'+esc(u.name || id)
                + ' <button type="button" title="Remove" aria-label="Remove">×</button></span>';
        }).join('');
        el.querySelectorAll('.tm-chip button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                onRemove(btn.parentNode.getAttribute('data-id'));
            });
        });
    }

    function addTaskCard() {
        var i = taskIndex++;
        var assignees = [];
        var ccs = [];
        var wrap = document.createElement('div');
        wrap.className = 'tm-task-card';
        wrap.dataset.index = String(i);
        wrap.innerHTML = ''
            + '<h5 style="color:#0b3f90;">Task '+(i+1)+'</h5>'
            + '<div class="row">'
            + '  <div class="col-md-9 form-group"><label>Subject <span class="text-danger">*</span></label>'
            + '    <input type="text" name="tasks['+i+'][subject]" class="form-control tm-subject" placeholder="Task subject" required></div>'
            + '  <div class="col-md-3 form-group"><label>Priority</label>'
            + '    <div class="btn-group-vertical btn-group-sm w-100" data-priority>'
            + '      <button type="button" class="btn btn-outline-secondary" data-val="Low">Low</button>'
            + '      <button type="button" class="btn btn-outline-secondary tm-priority-btn active" data-val="Medium">Medium</button>'
            + '      <button type="button" class="btn btn-outline-secondary" data-val="High">High</button>'
            + '      <button type="button" class="btn btn-outline-secondary" data-val="Emergency">Emergency</button>'
            + '    </div>'
            + '    <input type="hidden" name="tasks['+i+'][priority]" value="Medium" class="tm-priority-input">'
            + '  </div>'
            + '</div>'
            + '<div class="form-group"><label>Description</label>'
            + '  <textarea name="tasks['+i+'][description]" class="form-control tm-desc" rows="4" placeholder="Describe the task…"></textarea>'
            + '  <div class="mt-1 small">Insert: '
            + '    <span class="tm-ph" data-token="{Name}">{Name}</span>'
            + '    <span class="tm-ph" data-token="{Phone}">{Phone}</span>'
            + '    <span class="tm-ph" data-token="{Email}">{Email}</span>'
            + '    <span class="tm-ph" data-token="{Address}">{Address}</span>'
            + '  </div></div>'
            + '<div class="form-group"><label>Task Color</label><div class="tm-colors">'
            + colors.map(function (c, idx) {
                return '<span class="tm-color-dot'+(idx===0?' active':'')+'" data-color="'+c+'" style="background:'+c+'"></span>';
            }).join('')
            + '</div><input type="hidden" name="tasks['+i+'][color]" value="'+colors[0]+'" class="tm-color-input"></div>'
            + '<div class="row">'
            + '  <div class="col-md-3 form-group"><label>Start Date</label><input type="date" name="tasks['+i+'][start_date]" class="form-control"></div>'
            + '  <div class="col-md-3 form-group"><label>Start Time</label><input type="time" name="tasks['+i+'][start_time]" class="form-control"></div>'
            + '  <div class="col-md-3 form-group"><label>End Date *</label><input type="date" name="tasks['+i+'][end_date]" class="form-control" required></div>'
            + '  <div class="col-md-3 form-group"><label>End Time</label><input type="time" name="tasks['+i+'][end_time]" class="form-control"></div>'
            + '</div>'
            + '<div class="form-group"><label>PDF (optional)</label><input type="file" name="tasks['+i+'][pdf]" class="form-control-file" accept="application/pdf"></div>'
            + '<hr>'
            + '<div class="form-group"><label>Assign To <span class="text-danger">*</span></label>'
            + '  <div class="mb-2">'
            + '    <button type="button" class="btn btn-sm btn-primary tm-af" data-role="all">All Members</button> '
            + '    <button type="button" class="btn btn-sm btn-outline-primary tm-af" data-role="staff">Staff</button> '
            + '    <button type="button" class="btn btn-sm btn-outline-primary tm-af" data-role="customers">Customers</button>'
            + '  </div>'
            + '  <div class="d-flex mb-2" style="gap:8px;">'
            + '    <input type="search" class="form-control form-control-sm tm-asearch" placeholder="Search…">'
            + '    <button type="button" class="btn btn-sm btn-outline-secondary tm-aselect-all">Select everyone</button>'
            + '  </div>'
            + '  <div class="tm-user-list tm-alist"></div>'
            + '  <div class="tm-achips mt-2"></div>'
            + '  <div class="tm-ahiddens"></div>'
            + '  <small class="text-danger tm-aerr d-none">Pick at least one assignee.</small>'
            + '</div>'
            + '<div class="form-group"><label>CC (Carbon Copy)</label>'
            + '  <p class="text-muted small mb-1">Teachers or supervisors who should follow progress (not assignees).</p>'
            + '  <div class="mb-2">'
            + '    <button type="button" class="btn btn-sm btn-primary tm-cf" data-role="all">All Members</button> '
            + '    <button type="button" class="btn btn-sm btn-outline-primary tm-cf" data-role="staff">Staff</button> '
            + '    <button type="button" class="btn btn-sm btn-outline-primary tm-cf" data-role="customers">Customers</button>'
            + '  </div>'
            + '  <div class="d-flex mb-2" style="gap:8px;">'
            + '    <input type="search" class="form-control form-control-sm tm-csearch" placeholder="Search CC recipients…">'
            + '    <button type="button" class="btn btn-sm btn-outline-secondary tm-cselect-all">Select everyone</button>'
            + '  </div>'
            + '  <div class="tm-user-list tm-clist"></div>'
            + '  <div class="tm-cchips mt-2"></div>'
            + '  <div class="tm-chiddens"></div>'
            + '</div>'
            + '<div class="form-group"><label><i class="dripicons-clock"></i> Reminders</label>'
            + '  <p class="text-muted small">Multiple reminders before deadline — message shows time remaining.</p>'
            + '  <div class="tm-reminders"></div>'
            + '  <button type="button" class="btn btn-sm btn-outline-primary tm-add-reminder">+ Add reminder</button>'
            + '</div>'
            + '<div class="form-group"><label><i class="dripicons-clock"></i> When to Send</label>'
            + '  <div class="d-flex" style="gap:10px;">'
            + '    <div class="tm-send-opt active" data-mode="now">✈ Send immediately</div>'
            + '    <div class="tm-send-opt" data-mode="schedule">📅 Schedule</div>'
            + '  </div>'
            + '  <input type="hidden" name="tasks['+i+'][send_mode]" value="now" class="tm-send-mode">'
            + '  <input type="datetime-local" name="tasks['+i+'][schedule_at]" class="form-control mt-2 tm-schedule-at d-none">'
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
            });
            syncCcHiddens();
        }

        wrap.querySelector('.tm-asearch').addEventListener('input', refreshAssignees);
        wrap.querySelector('.tm-csearch').addEventListener('input', refreshCc);
        wrap.querySelectorAll('.tm-af').forEach(function (btn) {
            btn.addEventListener('click', function () {
                aRole = btn.getAttribute('data-role');
                wrap.querySelectorAll('.tm-af').forEach(function (b) { b.className = 'btn btn-sm btn-outline-primary tm-af'; });
                btn.className = 'btn btn-sm btn-primary tm-af';
                refreshAssignees();
            });
        });
        wrap.querySelectorAll('.tm-cf').forEach(function (btn) {
            btn.addEventListener('click', function () {
                cRole = btn.getAttribute('data-role');
                wrap.querySelectorAll('.tm-cf').forEach(function (b) { b.className = 'btn btn-sm btn-outline-primary tm-cf'; });
                btn.className = 'btn btn-sm btn-primary tm-cf';
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

        wrap.querySelectorAll('[data-priority] .btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                wrap.querySelectorAll('[data-priority] .btn').forEach(function (b) { b.classList.remove('active', 'tm-priority-btn'); b.classList.add('btn-outline-secondary'); });
                btn.classList.add('active', 'tm-priority-btn');
                wrap.querySelector('.tm-priority-input').value = btn.getAttribute('data-val');
            });
        });
        wrap.querySelectorAll('.tm-color-dot').forEach(function (dot) {
            dot.addEventListener('click', function () {
                wrap.querySelectorAll('.tm-color-dot').forEach(function (d) { d.classList.remove('active'); });
                dot.classList.add('active');
                wrap.querySelector('.tm-color-input').value = dot.getAttribute('data-color');
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
        wrap.querySelector('.tm-add-reminder').addEventListener('click', function () {
            var box = wrap.querySelector('.tm-reminders');
            var row = document.createElement('div');
            row.className = 'd-flex mb-2';
            row.style.gap = '8px';
            row.innerHTML = '<input type="datetime-local" name="tasks['+i+'][reminders][]" class="form-control">'
                + '<button type="button" class="btn btn-sm btn-outline-danger">×</button>';
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

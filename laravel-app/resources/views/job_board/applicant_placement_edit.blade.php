@extends('layout.main')
@section('content')
@php
    $programOptions = ($internshipPrograms ?? collect())->map(function ($p) {
        return [
            'id' => (string) $p->id,
            'name' => method_exists($p, 'displayName') ? $p->displayName() : $p->name,
            'code' => $p->code ?? '',
        ];
    })->values();
    $defaultProgramId = old('program_id', optional($enrolment)->program_id ?: optional($application)->internship_program_id);
    $defaultDuration = old('planned_duration_days', optional($enrolment)->planned_duration_days ?: optional($application)->internship_duration_days ?: 90);
    $defaultStartDay = old('start_curriculum_day', optional($enrolment)->start_curriculum_day ?: 1);
    $defaultStartDate = old('start_date', optional($enrolment)->start_date
        ? (\Carbon\Carbon::parse($enrolment->start_date)->format('Y-m-d'))
        : date('Y-m-d'));
@endphp
<style>
    .jb-pill {
        border:0; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600;
        background:#f1f5f9; color:#334155; cursor:pointer; margin:0 4px 6px 0;
    }
    .jb-pill.active { background:#0b3f90; color:#fff; }
    .jb-user-list { max-height:220px; overflow:auto; border:1px solid #e3e9f4; border-radius:10px; background:#fff; }
    .jb-user-item { display:block; width:100%; text-align:left; padding:10px 12px; border:0; border-bottom:1px solid #f0f3f8; background:#fff; cursor:pointer; }
    .jb-user-item:hover, .jb-user-item.selected { background:#f0f6ff; }
    .jb-user-item .meta { color:#6b7280; font-size:12px; }
    .jb-chip {
        display:inline-flex; align-items:center; gap:6px; border:1px solid #0b3f90; color:#0b3f90;
        background:#eef4ff; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:600; margin:2px;
    }
    .jb-chip button { border:0; background:transparent; color:#0b3f90; font-weight:800; cursor:pointer; line-height:1; }
    .jb-prog-selected {
        border:1px solid #0b3f90; background:#eef4ff; color:#0b3f90; border-radius:8px;
        padding:10px 12px; font-weight:700; min-height:42px;
    }
</style>
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <a href="{{ route('jobs.applicants', ['open' => $application->id]) }}" class="jb-btn-secondary mb-3" style="display:inline-flex;">&larr; Interns</a>

        <div class="mb-3">
            <h1 class="jb-title">Edit placement</h1>
            <p class="jb-subtitle mb-0">
                <strong>{{ $application->full_name }}</strong>
                · {{ $application->email }}
                · {{ str_replace('_', ' ', $application->status) }}
                @if($enrolment)
                    · Enrolment #{{ $enrolment->id }} ({{ $enrolment->status }})
                    · Progress {{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }}
                @else
                    · Not enrolled yet — saving will create the placement
                @endif
            </p>
        </div>

        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('jobs.applicants.placement.update', $application->id) }}" id="jb-placement-form">
            @csrf
            <input type="hidden" name="program_id" id="jb-program-id" value="{{ $defaultProgramId }}" required>

            <div class="jb-card">
                <div class="mb-3">
                    <label class="jb-label">Internship program *</label>
                    @if(!empty($hasAssignments))
                        <div class="jb-prog-selected" id="jb-program-chosen">Locked after tasks started</div>
                        <p class="text-muted small mt-1 mb-0">Program cannot change after tasks have been released. Pause and contact an admin if a transfer is needed.</p>
                        <input type="hidden" id="jb-program-locked" value="1">
                    @else
                        <div class="jb-prog-selected mb-2" id="jb-program-chosen">No program selected</div>
                        <input type="search" class="jb-field mb-2" id="jb-program-search" placeholder="Search programs…">
                        <div class="jb-user-list" id="jb-program-list" style="max-height:180px;"></div>
                    @endif
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap:8px;">
                        <label class="jb-label mb-0" style="color:#0b3f90;font-weight:700;font-size:15px;">Supervisors</label>
                        <div class="d-flex align-items-center flex-wrap" style="gap:10px;">
                            <button type="button" class="jb-btn-secondary" id="jb-add-supervisor-btn" style="white-space:nowrap;">
                                <i class="dripicons-plus"></i> Add supervisor
                            </button>
                            <div>
                                <small class="text-muted">Selected</small>
                                <strong id="jb-sup-count" style="color:#0b3f90;font-size:1.15rem;margin-left:6px;">0</strong>
                            </div>
                        </div>
                    </div>

                    <div id="jb-add-supervisor-panel" class="mb-3" style="display:none;border:1px solid #e3e9f4;border-radius:10px;padding:12px;background:#f8fafc;">
                        <p class="text-muted small mb-2">Add someone who is not listed. They are saved in <strong>People → Customers</strong> (system-wide) and selected automatically.</p>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold">Name *</label>
                                <input type="text" class="jb-field" id="jb-new-sup-name" placeholder="Full name">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold">Phone / WhatsApp *</label>
                                <input type="tel" class="jb-field" id="jb-new-sup-phone" placeholder="675321739 or +237…">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold">Email (optional)</label>
                                <input type="email" class="jb-field" id="jb-new-sup-email" placeholder="email@example.com">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold">Address (optional)</label>
                                <input type="text" class="jb-field" id="jb-new-sup-address" placeholder="City / address">
                            </div>
                        </div>
                        <div class="d-flex flex-wrap" style="gap:8px;">
                            <button type="button" class="jb-btn" id="jb-new-sup-save">Save &amp; select</button>
                            <button type="button" class="jb-btn-secondary" id="jb-new-sup-cancel">Cancel</button>
                            <span class="small text-muted align-self-center" id="jb-new-sup-status"></span>
                        </div>
                    </div>

                    <div class="mb-2">
                        <button type="button" class="jb-pill active jb-sf" data-role="customers">Customers</button>
                        <button type="button" class="jb-pill jb-sf" data-role="staff">System Users</button>
                        <button type="button" class="jb-pill jb-sf" data-role="all">All</button>
                    </div>
                    <div class="d-flex mb-2" style="gap:8px;">
                        <input type="search" class="jb-field jb-ssearch" placeholder="Search name, email, phone…">
                        <button type="button" class="jb-btn-secondary jb-sselect-all" style="white-space:nowrap;">Select all</button>
                    </div>
                    <div class="jb-user-list jb-slist"></div>
                    <div class="jb-schips mt-2"></div>
                    <div class="jb-shiddens"></div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Duration (days) *</label>
                        <input type="number" name="planned_duration_days" class="jb-field" required
                               min="{{ \App\Application::internshipDurationMin() }}"
                               max="{{ \App\Application::internshipDurationMax() }}"
                               value="{{ $defaultDuration }}"
                               @if(!empty($hasAssignments)) readonly @endif>
                        @if(!empty($hasAssignments))
                            <small class="text-muted">Duration is locked after tasks started.</small>
                        @endif
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Start from curriculum day *</label>
                        <input type="number" name="start_curriculum_day" class="jb-field" min="1" max="180" required
                               value="{{ $defaultStartDay }}"
                               @if(!empty($hasAssignments)) readonly @endif>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Calendar start date *</label>
                        <input type="date" name="start_date" class="jb-field" required value="{{ $defaultStartDate }}">
                    </div>
                    @if(!empty($hasAssignments) && empty($hasOpen) && $enrolment)
                        <div class="col-md-4 mb-3">
                            <label class="jb-label">Next curriculum day</label>
                            <input type="number" name="next_curriculum_day" class="jb-field"
                                   min="{{ $enrolment->startCurriculumDay() }}" max="{{ $enrolment->endCurriculumDay() }}"
                                   value="{{ old('next_curriculum_day', $enrolment->nextCurriculumDay() ?: $enrolment->startCurriculumDay()) }}">
                            <small class="text-muted">Jump ahead within days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }}.</small>
                        </div>
                    @endif
                    <div class="col-12 mb-3">
                        <label class="jb-label">Notes</label>
                        <textarea name="notes" class="jb-field" rows="2">{{ old('notes', optional($enrolment)->notes) }}</textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap" style="gap:10px;">
                    <button type="submit" class="jb-btn"><i class="dripicons-checkmark"></i> Save placement</button>
                    <a href="{{ route('jobs.applications.show', $application->id) }}" class="jb-btn-secondary">View application</a>
                    <a href="{{ route('jobs.applicants', ['open' => $application->id]) }}" class="jb-btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
window.JB_PROGRAMS = @json($programOptions);
window.JB_USERS = @json($directoryPeople ?? []);
window.JB_USERS_SEARCH = @json($directorySearchUrl ?? route('jobs.people.search'));
window.JB_QUICK_SUPERVISOR = @json($quickSupervisorUrl ?? route('jobs.supervisors.quick'));
window.JB_OLD_SUPERVISORS = @json(old('supervisor_ids', $selectedSupervisorIds ?? []));
window.JB_OLD_PROGRAM = @json((string) $defaultProgramId);
window.JB_CSRF = @json(csrf_token());
window.JB_PROGRAM_LOCKED = @json(!empty($hasAssignments));

(function () {
    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    var programIdInput = document.getElementById('jb-program-id');
    var programChosen = document.getElementById('jb-program-chosen');
    var programList = document.getElementById('jb-program-list');
    var selectedProgramId = window.JB_OLD_PROGRAM || '';

    function programLabel(p) {
        return (p.name || 'Program') + (p.code ? ' (' + p.code + ')' : '');
    }
    function setProgram(id) {
        if (window.JB_PROGRAM_LOCKED) return;
        selectedProgramId = String(id || '');
        programIdInput.value = selectedProgramId;
        var p = (window.JB_PROGRAMS || []).find(function (x) { return String(x.id) === selectedProgramId; });
        programChosen.textContent = p ? programLabel(p) : (selectedProgramId ? ('Program #' + selectedProgramId) : 'No program selected');
        if (programList) renderPrograms();
    }
    function renderPrograms() {
        if (!programList) return;
        var q = ((document.getElementById('jb-program-search') || {}).value || '').toLowerCase();
        var rows = (window.JB_PROGRAMS || []).filter(function (p) {
            if (!q) return true;
            return (p.name || '').toLowerCase().indexOf(q) !== -1 || (p.code || '').toLowerCase().indexOf(q) !== -1;
        });
        programList.innerHTML = rows.map(function (p) {
            var sel = String(p.id) === selectedProgramId ? ' selected' : '';
            return '<button type="button" class="jb-user-item'+sel+'" data-id="'+esc(p.id)+'">'
                + '<div class="font-weight-bold">'+esc(p.name||'Program')+'</div>'
                + '<div class="meta">'+esc(p.code||'')+'</div></button>';
        }).join('') || '<div class="p-3 text-muted small text-center">No programs found.</div>';
        programList.querySelectorAll('.jb-user-item').forEach(function (item) {
            item.addEventListener('click', function () { setProgram(item.getAttribute('data-id')); });
        });
    }
    if (!window.JB_PROGRAM_LOCKED) {
        var searchEl = document.getElementById('jb-program-search');
        if (searchEl) searchEl.addEventListener('input', renderPrograms);
        if (selectedProgramId) setProgram(selectedProgramId); else renderPrograms();
    } else {
        var p = (window.JB_PROGRAMS || []).find(function (x) { return String(x.id) === selectedProgramId; });
        if (programChosen && p) programChosen.textContent = programLabel(p);
    }

    var supervisors = (window.JB_OLD_SUPERVISORS || []).slice();
    var sRole = 'customers';
    var searchTimer = null;

    function mergeUsers(list) {
        var map = {};
        (window.JB_USERS || []).forEach(function (u) { map[u.id] = u; });
        (list || []).forEach(function (u) { map[u.id] = u; });
        window.JB_USERS = Object.keys(map).map(function (k) { return map[k]; });
    }
    function matchesRole(u, roleFilter) {
        var role = (u.role || '').toLowerCase();
        var source = (u.source || '').toLowerCase();
        if (roleFilter === 'staff') {
            return source !== 'customer' && source !== 'applicant' && role !== 'customer' && role !== 'client' && role !== 'applicant';
        }
        if (roleFilter === 'customers') {
            return source === 'customer' || role === 'customer' || role === 'client';
        }
        return source !== 'applicant' && role !== 'applicant';
    }
    function filterUsersLocal(query, roleFilter) {
        var q = (query || '').toLowerCase();
        return (window.JB_USERS || []).filter(function (u) {
            if (!matchesRole(u, roleFilter)) return false;
            if (!q) return true;
            return (u.name||'').toLowerCase().indexOf(q) !== -1
                || (u.email||'').toLowerCase().indexOf(q) !== -1
                || (u.phone||'').toLowerCase().indexOf(q) !== -1;
        });
    }
    function searchUsers(query, roleFilter, done) {
        var q = (query || '').trim();
        if (!q || q.length < 2 || !window.JB_USERS_SEARCH) {
            done(filterUsersLocal(query, roleFilter));
            return;
        }
        var filter = roleFilter === 'staff' ? 'staff' : (roleFilter === 'customers' ? 'customers' : 'all');
        fetch(window.JB_USERS_SEARCH + '?q=' + encodeURIComponent(q) + '&filter=' + encodeURIComponent(filter), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (rows) {
            mergeUsers(rows);
            done(filterUsersLocal(query, roleFilter));
        }).catch(function () { done(filterUsersLocal(query, roleFilter)); });
    }
    function refreshSupervisors() {
        var q = document.querySelector('.jb-ssearch').value;
        searchUsers(q, sRole, function (users) {
            var list = document.querySelector('.jb-slist');
            list.innerHTML = users.map(function (u) {
                var sel = supervisors.indexOf(u.id) !== -1 ? ' selected' : '';
                return '<button type="button" class="jb-user-item'+sel+'" data-id="'+esc(u.id)+'">'
                    + '<div class="font-weight-bold">'+esc(u.name||'Untitled')+' <span class="badge badge-light">'+esc(u.source||u.role||'')+'</span></div>'
                    + '<div class="meta">'+esc(u.email||'')+' · '+esc(u.phone||'')+'</div></button>';
            }).join('') || '<div class="p-3 text-muted small text-center">No people found.</div>';
            list.querySelectorAll('.jb-user-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    var id = item.getAttribute('data-id');
                    var i = supervisors.indexOf(id);
                    if (i === -1) supervisors.push(id); else supervisors.splice(i, 1);
                    refreshSupervisors();
                });
            });
            var map = {};
            (window.JB_USERS || []).forEach(function (u) { map[u.id] = u; });
            document.querySelector('.jb-schips').innerHTML = supervisors.map(function (id) {
                var u = map[id] || { name: id };
                return '<span class="jb-chip" data-id="'+esc(id)+'">'+esc(u.name||id)+' <button type="button">×</button></span>';
            }).join('');
            document.querySelectorAll('.jb-schips .jb-chip button').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var id = btn.parentNode.getAttribute('data-id');
                    supervisors = supervisors.filter(function (x) { return x !== id; });
                    refreshSupervisors();
                });
            });
            document.querySelector('.jb-shiddens').innerHTML = supervisors.map(function (id) {
                return '<input type="hidden" name="supervisor_ids[]" value="'+esc(id)+'">';
            }).join('');
            document.getElementById('jb-sup-count').textContent = String(supervisors.length);
        });
    }
    document.querySelector('.jb-ssearch').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(refreshSupervisors, 250);
    });
    document.querySelectorAll('.jb-sf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            sRole = btn.getAttribute('data-role');
            document.querySelectorAll('.jb-sf').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshSupervisors();
        });
    });
    document.querySelector('.jb-sselect-all').addEventListener('click', function () {
        filterUsersLocal(document.querySelector('.jb-ssearch').value, sRole).forEach(function (u) {
            if (supervisors.indexOf(u.id) === -1) supervisors.push(u.id);
        });
        refreshSupervisors();
    });

    var addPanel = document.getElementById('jb-add-supervisor-panel');
    var addBtn = document.getElementById('jb-add-supervisor-btn');
    var addStatus = document.getElementById('jb-new-sup-status');
    if (addBtn && addPanel) {
        addBtn.addEventListener('click', function () {
            addPanel.style.display = addPanel.style.display === 'none' ? 'block' : 'none';
            if (addPanel.style.display === 'block') document.getElementById('jb-new-sup-name').focus();
        });
        document.getElementById('jb-new-sup-cancel').addEventListener('click', function () {
            addPanel.style.display = 'none';
            if (addStatus) addStatus.textContent = '';
        });
        document.getElementById('jb-new-sup-save').addEventListener('click', function () {
            var name = (document.getElementById('jb-new-sup-name').value || '').trim();
            var phone = (document.getElementById('jb-new-sup-phone').value || '').trim();
            var email = (document.getElementById('jb-new-sup-email').value || '').trim();
            var address = (document.getElementById('jb-new-sup-address').value || '').trim();
            if (!name || !phone) { alert('Name and phone are required.'); return; }
            var btn = document.getElementById('jb-new-sup-save');
            btn.disabled = true;
            if (addStatus) addStatus.textContent = 'Saving…';
            fetch(window.JB_QUICK_SUPERVISOR, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.JB_CSRF || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name: name, phone: phone, email: email, address: address })
            }).then(function (r) {
                return r.json().then(function (body) {
                    if (!r.ok) {
                        var msg = (body && body.message) ? body.message : 'Could not save supervisor.';
                        throw new Error(msg);
                    }
                    return body;
                });
            }).then(function (body) {
                var u = body.user;
                if (!u || !u.id) throw new Error('Invalid response.');
                mergeUsers([u]);
                if (supervisors.indexOf(u.id) === -1) supervisors.push(u.id);
                sRole = 'customers';
                document.querySelectorAll('.jb-sf').forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-role') === 'customers');
                });
                document.getElementById('jb-new-sup-name').value = '';
                document.getElementById('jb-new-sup-phone').value = '';
                document.getElementById('jb-new-sup-email').value = '';
                document.getElementById('jb-new-sup-address').value = '';
                addPanel.style.display = 'none';
                if (addStatus) {
                    addStatus.textContent = body.created
                        ? ('Saved to Customers' + (body.customer_id ? ' #' + body.customer_id : '') + ' and selected.')
                        : ('Existing Customer' + (body.customer_id ? ' #' + body.customer_id : '') + ' selected.');
                }
                refreshSupervisors();
            }).catch(function (err) {
                alert(err.message || 'Could not save supervisor.');
                if (addStatus) addStatus.textContent = '';
            }).finally(function () { btn.disabled = false; });
        });
    }

    // Prefetch selected supervisors so chips show names
    if (supervisors.length && window.JB_USERS_SEARCH) {
        fetch(window.JB_USERS_SEARCH + '?q=&filter=all', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (rows) {
            mergeUsers(rows);
            refreshSupervisors();
        }).catch(function () { refreshSupervisors(); });
    } else {
        refreshSupervisors();
    }

    document.getElementById('jb-placement-form').addEventListener('submit', function (e) {
        if (!programIdInput.value) {
            e.preventDefault();
            alert('Select an internship program.');
        }
    });
})();
</script>
@endsection

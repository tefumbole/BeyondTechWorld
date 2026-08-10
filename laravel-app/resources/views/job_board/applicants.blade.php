@extends('layout.main')
@section('content')
@php
    $openId = (string) ($openId ?? '');
    $people = $people ?? collect();
    $programOptions = ($internshipPrograms ?? collect())->map(function ($p) {
        return [
            'id' => (string) $p->id,
            'name' => method_exists($p, 'displayName') ? $p->displayName() : $p->name,
            'code' => $p->code ?? '',
        ];
    })->values();
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
    .jb-row-click { cursor: pointer; }
    .jb-row-click:hover { background: #f8fafc; }
    .jb-row-click.is-open { background: #eef4ff; }
    .jb-interns-card { overflow: hidden; }
    .jb-interns-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .jb-interns-table {
        width: 100%;
        min-width: 780px;
        table-layout: fixed;
        margin: 0;
    }
    .jb-interns-table th, .jb-interns-table td {
        vertical-align: middle; font-size: 13px; padding: 8px 8px;
    }
    .jb-interns-table th:not(.jb-actions),
    .jb-interns-table td:not(.jb-actions) {
        overflow: hidden; text-overflow: ellipsis;
    }
    .jb-interns-table th { white-space: nowrap; }
    .jb-interns-table .jb-contact {
        font-size: 12px; color: #64748b; line-height: 1.35;
        max-width: 140px; word-break: break-all; white-space: normal;
    }
    .jb-interns-table .jb-applied { width: 88px; max-width: 88px; white-space: nowrap; }
    .jb-interns-table .jb-actions {
        width: 168px;
        min-width: 168px;
        white-space: nowrap;
        overflow: visible;
    }
    .jb-interns-table .jb-actions .jb-act {
        display: inline-flex; align-items: center; justify-content: center;
        padding: 4px 8px !important; font-size: 12px !important; gap: 0;
        min-width: 0;
    }
</style>
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')
        <div class="mb-3">
            <h1 class="jb-title">Interns</h1>
        </div>

        @if($openId !== '' && $application)
            <div class="jb-card">
                <div class="d-flex flex-wrap justify-content-between align-items-start mb-2" style="gap:10px;">
                    <div>
                        <h4 class="mb-1" style="color:#0b3f90;font-weight:800;">{{ $application->full_name }}</h4>
                        <div class="text-muted">
                            {{ $application->email }}
                            · {{ $application->whatsapp_number ?: $application->phone ?: '—' }}
                            · <span class="jb-badge {{ $application->statusBadgeClass() }}">{{ $application->statusLabel() }}</span>
                            @if(optional($application->job)->title)
                                · {{ $application->job->title }}
                            @endif
                        </div>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <a class="jb-btn-secondary" href="{{ route('jobs.applications.show', $application->id) }}">Open application</a>
                        <a class="jb-btn" href="{{ route('jobs.applicants.placement.edit', $application->id) }}">
                            <i class="dripicons-document-edit"></i> Edit placement / dates / supervisors
                        </a>
                        <a class="jb-btn-secondary" href="{{ route('jobs.applicants', array_filter(['q' => $q ?? null])) }}">Close tab</a>
                    </div>
                </div>
                @if($enrolment)
                    <div class="alert alert-light border mb-0">
                        <strong>Internship placement:</strong>
                        {{ optional($enrolment->program)->displayName() ?: optional($enrolment->program)->name ?: '—' }}
                        · Days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }}
                        ({{ $enrolment->plannedDurationDays() }} days)
                        · Starts {{ $enrolment->start_date ?: '—' }}
                        · Progress {{ $enrolment->completed_count }}/{{ $enrolment->plannedDurationDays() }}
                        · Supervisor {{ optional($enrolment->supervisor)->name ?: '—' }}
                        @if(count($enrolment->supervisorUserIds()) > 1)
                            <span class="text-muted">(+{{ count($enrolment->supervisorUserIds()) - 1 }} more)</span>
                        @endif
                    </div>
                @elseif($application->internship_program_id || $application->internship_duration_days)
                    <div class="alert alert-warning mb-0">
                        Program/duration on application, but no active enrolment yet. Use <strong>Edit placement</strong> to enrol.
                        @if($application->internship_duration_days)
                            Requested: {{ $application->internship_duration_days }} days.
                        @endif
                    </div>
                @else
                    <div class="alert alert-secondary mb-0">
                        No placement yet. Check this intern below and use <strong>Assign to internship</strong>, or open <strong>Edit placement</strong>.
                    </div>
                @endif
            </div>
        @endif

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-warning">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="jb-card">
            <form method="GET" action="{{ route('jobs.applicants') }}" class="d-flex flex-wrap align-items-center" style="gap:10px;">
                @if($openId !== '')
                    <input type="hidden" name="open" value="{{ $openId }}">
                @endif
                <input type="search" name="q" value="{{ $q }}" class="jb-field" placeholder="Search interns by name, email, phone…" style="max-width:360px;">
                <button type="submit" class="jb-btn"><i class="dripicons-search"></i> Search</button>
            </form>
        </div>

        <div class="jb-card jb-interns-card">
            <div class="jb-interns-wrap">
                <table class="table table-hover jb-interns-table">
                    <thead>
                        <tr>
                            <th style="width:34px;">
                                <input type="checkbox" id="jb-check-all" title="Select all">
                            </th>
                            <th style="width:34px;">#</th>
                            <th style="width:18%;">Name</th>
                            <th style="width:140px;">Contact</th>
                            <th style="width:44px;">Apps</th>
                            <th style="width:80px;">Status</th>
                            <th style="width:88px;">Applied</th>
                            <th class="jb-actions text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($people as $i => $person)
                            @php
                                $pid = (string) $person['latest_application_id'];
                                $openUrl = route('jobs.applicants', array_filter(['open' => $pid, 'q' => $q ?? null]));
                            @endphp
                            <tr class="jb-row-click {{ $openId === $pid ? 'is-open' : '' }}" data-open-url="{{ $openUrl }}">
                                <td onclick="event.stopPropagation();">
                                    <input type="checkbox" class="jb-row-check" form="assign-internship-form"
                                           name="application_ids[]"
                                           value="{{ $person['latest_application_id'] }}">
                                </td>
                                <td>{{ $i + 1 }}</td>
                                <td title="{{ $person['full_name'] }}"><strong>{{ $person['full_name'] }}</strong></td>
                                <td class="jb-contact" title="{{ trim(($person['email'] ?: '').' '.($person['phone'] ?: '').' '.($person['country'] ?: '')) }}">
                                    <div>{{ $person['email'] ?: '—' }}</div>
                                    <div>{{ $person['phone'] ?: '—' }}</div>
                                </td>
                                <td>{{ $person['applications_count'] }}</td>
                                <td><span class="jb-badge {{ \App\Application::badgeClassForStatus($person['latest_status'] ?? '') }}">{{ str_replace('_', ' ', $person['latest_status'] ?? '') }}</span></td>
                                <td class="jb-applied">{{ $person['submitted_at'] ? \Carbon\Carbon::parse($person['submitted_at'])->format('Y-m-d') : '—' }}</td>
                                <td class="jb-actions text-right" onclick="event.stopPropagation();">
                                    <a class="jb-btn jb-act" href="{{ $openUrl }}" title="Open intern tab">Open</a>
                                    <a class="jb-btn-secondary jb-act" href="{{ route('jobs.applications.show', $person['latest_application_id']) }}" title="View application">View</a>
                                    <a class="jb-btn jb-act" href="{{ route('jobs.applicants.placement.edit', $person['latest_application_id']) }}" title="Edit placement">
                                        <i class="dripicons-document-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No interns found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form id="assign-internship-form" method="POST" action="{{ route('jobs.applicants.assign') }}">
            @csrf
            <input type="hidden" name="program_id" id="jb-program-id" value="{{ old('program_id') }}" required>

            <div class="jb-card" id="assign-panel">
                <h5 style="color:#0b3f90;font-weight:800;margin:0 0 12px;">Assign to internship</h5>
                <p class="text-muted small mb-3">Checked interns will be enrolled for the chosen program, supervisor(s), and duration. ERP Intern accounts are created when needed.</p>

                <div class="mb-3">
                    <label class="jb-label">Internship program *</label>
                    <div class="jb-prog-selected mb-2" id="jb-program-chosen">{{ old('program_id') ? 'Program #'.old('program_id') : 'No program selected' }}</div>
                    <input type="search" class="jb-field mb-2" id="jb-program-search" placeholder="Search programs…">
                    <div class="jb-user-list" id="jb-program-list" style="max-height:180px;"></div>
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
                    <p class="text-muted small mb-2">Search and select one or more supervisors from System Users or Customers. Use + Add supervisor if they are not in the system yet.</p>

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
                            <button type="button" class="jb-btn" id="jb-new-sup-save"><i class="dripicons-checkmark"></i> Save &amp; select</button>
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
                               value="{{ old('planned_duration_days', 90) }}"
                               placeholder="e.g. 19, 21, 30, 90…">
                        <small class="text-muted">Any length from {{ \App\Application::internshipDurationMin() }} to {{ \App\Application::internshipDurationMax() }} days.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Start from curriculum day *</label>
                        <input type="number" name="start_curriculum_day" class="jb-field" min="1" max="180" required
                               value="{{ old('start_curriculum_day', 1) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Calendar start date *</label>
                        <input type="date" name="start_date" class="jb-field" required value="{{ old('start_date', date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <label class="d-flex align-items-center mb-2" style="gap:8px;font-weight:600;">
                            <input type="checkbox" name="mark_selected" value="1" @if(old('mark_selected', '1')) checked @endif>
                            Mark awaiting interns as Selected
                        </label>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
                    <button type="submit" class="jb-btn" id="assign-submit" disabled>
                        <i class="dripicons-graduation"></i> Assign selected (<span id="assign-count">0</span>)
                    </button>
                    <button type="submit" form="notify-interns-form" class="jb-btn" id="notify-submit" disabled
                            style="background:#e91e8c;border-color:#e91e8c;">
                        <i class="dripicons-message"></i> Notify (<span id="notify-count">0</span>)
                    </button>
                    <button type="button" class="jb-btn-secondary" id="jb-select-all">Select all interns</button>
                    <button type="button" class="jb-btn-secondary" id="jb-clear-all">Clear interns</button>
                    <a class="jb-btn-secondary" href="{{ route('letter.template.index') }}" target="_blank" rel="noopener">
                        Edit acceptance letter template
                    </a>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    <strong>Notify</strong> sends a signed Internship Acceptance letter (letterhead + signature + QR) on WhatsApp,
                    using program, supervisors, dates, login phone/email, and password <code>system</code>.
                    Edit the wording anytime under Letters → Templates → <em>Internship Acceptance Letter</em>.
                </p>
            </div>
        </form>

        <form id="notify-interns-form" method="POST" action="{{ route('jobs.applicants.notify') }}" class="d-none">
            @csrf
        </form>
    </div>
</section>

<script>
window.JB_PROGRAMS = @json($programOptions);
window.JB_USERS = @json($directoryPeople ?? []);
window.JB_USERS_SEARCH = @json($directorySearchUrl ?? route('jobs.people.search'));
window.JB_QUICK_SUPERVISOR = @json($quickSupervisorUrl ?? route('jobs.supervisors.quick'));
window.JB_OLD_SUPERVISORS = @json(old('supervisor_ids', []));
window.JB_OLD_PROGRAM = @json((string) old('program_id', ''));
window.JB_CSRF = @json(csrf_token());

(function () {
    document.querySelectorAll('.jb-row-click').forEach(function (row) {
        row.addEventListener('click', function () {
            var url = row.getAttribute('data-open-url');
            if (url) window.location.href = url;
        });
    });

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    /* —— Program searchable single-select —— */
    var programIdInput = document.getElementById('jb-program-id');
    var programChosen = document.getElementById('jb-program-chosen');
    var programList = document.getElementById('jb-program-list');
    var selectedProgramId = window.JB_OLD_PROGRAM || '';

    function programLabel(p) {
        return (p.name || 'Program') + (p.code ? ' (' + p.code + ')' : '');
    }

    function setProgram(id) {
        selectedProgramId = String(id || '');
        programIdInput.value = selectedProgramId;
        var p = (window.JB_PROGRAMS || []).find(function (x) { return String(x.id) === selectedProgramId; });
        programChosen.textContent = p ? programLabel(p) : (selectedProgramId ? ('Program #' + selectedProgramId) : 'No program selected');
        renderPrograms();
        syncAssignBtn();
    }

    function renderPrograms() {
        var q = (document.getElementById('jb-program-search').value || '').toLowerCase();
        var rows = (window.JB_PROGRAMS || []).filter(function (p) {
            if (!q) return true;
            return (p.name || '').toLowerCase().indexOf(q) !== -1
                || (p.code || '').toLowerCase().indexOf(q) !== -1;
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
    document.getElementById('jb-program-search').addEventListener('input', renderPrograms);
    if (selectedProgramId) setProgram(selectedProgramId); else renderPrograms();

    /* —— Multi supervisor picker (Users + Customers) —— */
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
        }).catch(function () {
            done(filterUsersLocal(query, roleFilter));
        });
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
                return '<span class="jb-chip" data-id="'+esc(id)+'">'+esc(u.name||id)
                    + ' <button type="button">×</button></span>';
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
            if (addPanel.style.display === 'none') {
                addPanel.style.display = 'block';
                document.getElementById('jb-new-sup-name').focus();
            } else {
                addPanel.style.display = 'none';
            }
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
            if (!name || !phone) {
                alert('Name and phone / WhatsApp are required.');
                return;
            }
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
                        if (body && body.errors) {
                            var first = Object.keys(body.errors)[0];
                            if (first && body.errors[first] && body.errors[first][0]) {
                                msg = body.errors[first][0];
                            }
                        }
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
            }).finally(function () {
                btn.disabled = false;
            });
        });
    }

    refreshSupervisors();

    /* —— Applicant row selection —— */
    var form = document.getElementById('assign-internship-form');
    var notifyForm = document.getElementById('notify-interns-form');
    var checkAll = document.getElementById('jb-check-all');
    var submitBtn = document.getElementById('assign-submit');
    var notifyBtn = document.getElementById('notify-submit');
    var countEl = document.getElementById('assign-count');
    var notifyCountEl = document.getElementById('notify-count');

    function rowChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.jb-row-check'));
    }
    function syncAssignBtn() {
        var n = rowChecks().filter(function (c) { return c.checked; }).length;
        var hasProgram = !!(programIdInput && programIdInput.value);
        if (submitBtn) submitBtn.disabled = n < 1 || !hasProgram;
        if (notifyBtn) notifyBtn.disabled = n < 1;
        if (countEl) countEl.textContent = String(n);
        if (notifyCountEl) notifyCountEl.textContent = String(n);
        if (checkAll) {
            var checks = rowChecks();
            checkAll.checked = checks.length > 0 && checks.every(function (c) { return c.checked; });
            checkAll.indeterminate = n > 0 && !checkAll.checked;
        }
    }
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowChecks().forEach(function (c) { c.checked = checkAll.checked; });
            syncAssignBtn();
        });
    }
    document.getElementById('jb-select-all').addEventListener('click', function () {
        rowChecks().forEach(function (c) { c.checked = true; });
        syncAssignBtn();
    });
    document.getElementById('jb-clear-all').addEventListener('click', function () {
        rowChecks().forEach(function (c) { c.checked = false; });
        syncAssignBtn();
    });
    rowChecks().forEach(function (c) { c.addEventListener('change', syncAssignBtn); });

    form.addEventListener('submit', function (e) {
        var n = rowChecks().filter(function (c) { return c.checked; }).length;
        if (!n) {
            e.preventDefault();
            alert('Select at least one intern.');
            return;
        }
        if (!programIdInput.value) {
            e.preventDefault();
            alert('Select an internship program.');
            return;
        }
        if (!confirm('Assign ' + n + ' intern(s) to this internship program?')) {
            e.preventDefault();
        }
    });

    if (notifyForm) {
        notifyForm.addEventListener('submit', function (e) {
            var selected = rowChecks().filter(function (c) { return c.checked; });
            if (!selected.length) {
                e.preventDefault();
                alert('Select at least one intern to notify.');
                return;
            }
            if (!confirm('Send signed Internship Acceptance letter(s) to ' + selected.length + ' intern(s) via WhatsApp?')) {
                e.preventDefault();
                return;
            }
            notifyForm.querySelectorAll('input[name="application_ids[]"]').forEach(function (el) { el.remove(); });
            selected.forEach(function (c) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'application_ids[]';
                input.value = c.value;
                notifyForm.appendChild(input);
            });
        });
    }

    syncAssignBtn();
})();
</script>
@endsection

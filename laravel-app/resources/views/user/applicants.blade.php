@extends('layout.main')
@section('content')
@php
    $openId = (string) ($openId ?? '');
    $directory = $directory ?? collect();
@endphp
<style>
    .ua-tabs {
        display: flex; flex-wrap: nowrap; gap: 8px; overflow-x: auto; padding: 4px 0 12px;
        -webkit-overflow-scrolling: touch;
    }
    .ua-tab {
        flex: 0 0 auto; display: inline-flex; align-items: center; gap: 6px;
        border: 2px solid #cbd5e1; background: #fff; color: #475569;
        border-radius: 10px; padding: 8px 12px; font-size: 13px; font-weight: 700;
        text-decoration: none !important; max-width: 220px; white-space: nowrap;
    }
    .ua-tab:hover { border-color: #0b3f90; color: #0b3f90; text-decoration: none !important; }
    .ua-tab.is-active { background: #0b3f90; border-color: #0b3f90; color: #fff !important; }
    .ua-tab .ua-meta { font-weight: 500; opacity: .85; font-size: 11px; overflow: hidden; text-overflow: ellipsis; }
    .ua-tab .ua-name { overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
    .ua-panel {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 1rem 1.25rem; margin-bottom: 1rem;
    }
    .ua-row-click { cursor: pointer; }
    .ua-row-click:hover { background: #f8fafc; }
    .ua-row-click.is-open { background: #eef4ff; }
</style>

@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<section>
    <div class="container-fluid mb-3">
        <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
            <a href="{{ route('user.index') }}" class="btn {{ ($category ?? '') === 'all' ? 'btn-info' : 'btn-outline-info' }}">All Users</a>
            <a href="{{ route('user.index', ['category' => 'applicants']) }}" class="btn {{ ($category ?? '') === 'applicants' ? 'btn-info' : 'btn-outline-info' }}">Interns</a>
            @if(in_array("users-add", $all_permission))
                <a href="{{route('user.create')}}" class="btn btn-default"><i class="dripicons-plus"></i> {{trans('file.Add User')}}</a>
            @endif
        </div>
        <p class="text-muted mt-2 mb-0">Job Board / Internship applications. Open an intern as a sub-tab to edit role, placement, dates, or supervisors — other interns stay visible above.</p>
    </div>

    <div class="container-fluid">
        <nav class="ua-tabs" aria-label="Intern sub-tabs">
            <a class="ua-tab {{ $openId === '' ? 'is-active' : '' }}"
               href="{{ route('user.index', array_filter(['category' => 'applicants', 'q' => $q ?? null])) }}">
                <span>All interns</span>
                <span class="ua-meta">({{ $directory->count() }})</span>
            </a>
            @foreach($directory as $person)
                @php $pid = (string) $person['latest_application_id']; @endphp
                <a class="ua-tab {{ $openId === $pid ? 'is-active' : '' }}"
                   href="{{ route('user.index', array_filter(['category' => 'applicants', 'open' => $pid, 'q' => $q ?? null])) }}"
                   title="{{ $person['full_name'] }}">
                    <span class="ua-name">{{ $person['full_name'] }}</span>
                    <span class="ua-meta">{{ str_replace('_', ' ', $person['latest_status']) }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    @if($openId !== '' && $application)
        <div class="container-fluid">
            <div class="ua-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-start mb-3" style="gap:10px;">
                    <div>
                        <h4 class="mb-1" style="color:#0b3f90;font-weight:800;">{{ $application->full_name }}</h4>
                        <div class="text-muted">
                            {{ $application->email }}
                            · {{ $application->whatsapp_number ?: $application->phone ?: '—' }}
                            · <span class="badge badge-secondary">{{ str_replace('_', ' ', $application->status) }}</span>
                            @if(!empty(optional($linkedUser)->role_id))
                                @php $roleName = optional(\App\Roles::find($linkedUser->role_id))->name; @endphp
                                @if($roleName)
                                    · <span class="badge badge-success">{{ $roleName }}</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <a class="btn btn-outline-info btn-sm" href="{{ route('jobs.applications.show', $application->id) }}">Open application</a>
                        <a class="btn btn-primary btn-sm" href="{{ route('jobs.applicants.placement.edit', $application->id) }}">
                            <i class="dripicons-graduation"></i> Edit placement / dates / supervisors
                        </a>
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('user.index', array_filter(['category' => 'applicants', 'q' => $q ?? null])) }}">Close tab</a>
                    </div>
                </div>

                @if($enrolment)
                    <div class="alert alert-light border mb-3">
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
                    <div class="alert alert-warning mb-3">
                        Program/duration on application, but no active enrolment yet.
                        Use <strong>Edit placement</strong> to enrol.
                        @if($application->internship_duration_days)
                            Requested: {{ $application->internship_duration_days }} days.
                        @endif
                    </div>
                @endif

                <h5 style="color:#0b3f90;font-weight:700;">Edit intern &amp; assign role</h5>
                <form method="POST" action="{{ route('user.applicants.update', $application->id) }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Full name *</label>
                            <input type="text" name="full_name" class="form-control" required
                                   value="{{ old('full_name', $application->full_name) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="{{ old('email', $application->email) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Phone / WhatsApp</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone', $application->whatsapp_number ?: $application->phone) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Assign ERP role</label>
                            <select name="role_id" class="form-control">
                                <option value="">— No role change —</option>
                                @foreach($assignableRoles as $r)
                                    <option value="{{ $r->id }}"
                                        @if(old('role_id', optional($linkedUser)->role_id) == $r->id) selected @endif>
                                        {{ $r->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Creates an ERP login if none exists. Use <strong>Intern</strong> for internship students.</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Warehouse</label>
                            <select name="warehouse_id" class="form-control">
                                <option value="">Default first warehouse</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" @if(old('warehouse_id', optional($linkedUser)->warehouse_id)==$w->id) selected @endif>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Biller</label>
                            <select name="biller_id" class="form-control">
                                <option value="">Default first biller</option>
                                @foreach($billers as $b)
                                    <option value="{{ $b->id }}" @if(old('biller_id', optional($linkedUser)->biller_id)==$b->id) selected @endif>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Password (optional)</label>
                            <input type="text" name="password" class="form-control" placeholder="Blank = auto-generate if new"
                                   value="{{ old('password') }}">
                        </div>
                        <div class="form-group col-md-12">
                            <label class="d-flex align-items-center" style="gap:8px;">
                                <input type="checkbox" name="is_active" value="1" @if(old('is_active', optional($linkedUser)->is_active ?? true)) checked @endif>
                                Active ERP account
                            </label>
                        </div>
                    </div>

                    @if($linkedUser)
                        <div class="alert alert-info">
                            Linked ERP user: <strong>{{ $linkedUser->name }}</strong> (ID {{ $linkedUser->id }}).
                        </div>
                    @else
                        <div class="alert alert-secondary">No ERP user linked yet. Choosing a role will create one.</div>
                    @endif

                    <button type="submit" class="btn btn-primary">Save intern</button>
                </form>
            </div>
        </div>
    @endif

    <div class="container-fluid mb-2 d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
        <h5 class="mb-0">{{ $openId !== '' ? 'All interns' : 'Interns' }}</h5>
        <div class="d-flex flex-wrap" style="gap:8px;">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="app-select-all">Select all</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="app-clear-all">Clear</button>
            <button type="submit" form="apps-delete-form" class="btn btn-danger btn-sm" id="app-delete-btn" disabled>
                <i class="dripicons-trash"></i> Delete selected
            </button>
        </div>
    </div>

    <div class="container-fluid mb-3">
        <form method="GET" action="{{ route('user.index') }}" class="form-inline" style="gap:8px;">
            <input type="hidden" name="category" value="applicants">
            @if($openId !== '')
                <input type="hidden" name="open" value="{{ $openId }}">
            @endif
            <input type="search" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search applications…" style="min-width:260px;">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <form id="apps-delete-form" method="POST" action="{{ route('user.applicants.delete') }}">
        @csrf
        <div class="table-responsive container-fluid">
            <table class="table" id="apps-table">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" id="app-check-all" title="Select all">
                        </th>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Applications</th>
                        <th>Latest status</th>
                        <th>ERP role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($directory as $i => $person)
                        @php
                            $ids = $person['application_ids'] ?? [$person['latest_application_id']];
                            $idsAttr = implode(',', $ids);
                            $pid = (string) $person['latest_application_id'];
                            $openUrl = route('user.index', array_filter(['category' => 'applicants', 'open' => $pid, 'q' => $q ?? null]));
                        @endphp
                        <tr class="ua-row-click {{ $openId === $pid ? 'is-open' : '' }}" data-open-url="{{ $openUrl }}">
                            <td onclick="event.stopPropagation();">
                                <input type="checkbox" class="app-row-check" name="application_ids[]" value="{{ $idsAttr }}">
                            </td>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $person['full_name'] }}</strong></td>
                            <td>{{ $person['email'] ?: '—' }}</td>
                            <td>{{ $person['phone'] ?: '—' }}</td>
                            <td>{{ $person['applications_count'] }}</td>
                            <td>{{ str_replace('_', ' ', $person['latest_status']) }}</td>
                            <td>
                                @if(!empty($person['erp_role']))
                                    <span class="badge badge-success">{{ $person['erp_role'] }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;" onclick="event.stopPropagation();">
                                <a href="{{ $openUrl }}" class="btn btn-sm btn-primary">Open</a>
                                <a href="{{ route('jobs.applicants.placement.edit', $pid) }}" class="btn btn-sm btn-outline-primary">Placement</a>
                                <a href="{{ route('jobs.applications.show', $pid) }}" class="btn btn-sm btn-link">Application</a>
                                <button type="button" class="btn btn-sm btn-link text-danger app-delete-one" data-ids="{{ $idsAttr }}">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">No applications found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</section>

<script>
(function () {
    var activeTab = document.querySelector('.ua-tab.is-active');
    if (activeTab && activeTab.scrollIntoView) {
        activeTab.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    }

    document.querySelectorAll('.ua-row-click').forEach(function (row) {
        row.addEventListener('click', function () {
            var url = row.getAttribute('data-open-url');
            if (url) window.location.href = url;
        });
    });

    var form = document.getElementById('apps-delete-form');
    var checkAll = document.getElementById('app-check-all');
    var deleteBtn = document.getElementById('app-delete-btn');
    if (!form || !deleteBtn) return;

    function rowChecks() {
        return Array.prototype.slice.call(document.querySelectorAll('.app-row-check'));
    }

    function syncDeleteBtn() {
        var any = rowChecks().some(function (c) { return c.checked; });
        deleteBtn.disabled = !any;
        if (checkAll) {
            var all = rowChecks();
            checkAll.checked = all.length > 0 && all.every(function (c) { return c.checked; });
            checkAll.indeterminate = any && !checkAll.checked;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowChecks().forEach(function (c) { c.checked = checkAll.checked; });
            syncDeleteBtn();
        });
    }

    document.getElementById('app-select-all') && document.getElementById('app-select-all').addEventListener('click', function () {
        rowChecks().forEach(function (c) { c.checked = true; });
        syncDeleteBtn();
    });
    document.getElementById('app-clear-all') && document.getElementById('app-clear-all').addEventListener('click', function () {
        rowChecks().forEach(function (c) { c.checked = false; });
        syncDeleteBtn();
    });

    rowChecks().forEach(function (c) {
        c.addEventListener('change', syncDeleteBtn);
    });

    form.addEventListener('submit', function (e) {
        var n = rowChecks().filter(function (c) { return c.checked; }).length;
        if (!n) {
            e.preventDefault();
            alert('Select at least one intern to delete.');
            return;
        }
        if (!confirm('Delete all applications for the ' + n + ' selected intern(s)? This cannot be undone.')) {
            e.preventDefault();
        }
    });

    document.querySelectorAll('.app-delete-one').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ids = btn.getAttribute('data-ids') || '';
            if (!ids) return;
            if (!confirm('Delete all applications for this intern? This cannot be undone.')) return;
            rowChecks().forEach(function (c) { c.checked = false; });
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'application_ids[]';
            input.value = ids;
            form.appendChild(input);
            form.submit();
        });
    });

    syncDeleteBtn();
})();
</script>
@endsection

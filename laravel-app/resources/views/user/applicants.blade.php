@extends('layout.main')
@section('content')
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid mb-3">
        <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
            <a href="{{ route('user.index') }}" class="btn {{ ($category ?? '') === 'all' ? 'btn-info' : 'btn-outline-info' }}">All Users</a>
            <a href="{{ route('user.index', ['category' => 'applicants']) }}" class="btn {{ ($category ?? '') === 'applicants' ? 'btn-info' : 'btn-outline-info' }}">Applicants</a>
            @if(in_array("users-add", $all_permission))
                <a href="{{route('user.create')}}" class="btn btn-default"><i class="dripicons-plus"></i> {{trans('file.Add User')}}</a>
            @endif
        </div>
        <p class="text-muted mt-2 mb-0">Applicants are people who applied via Job Board / Internships (separate from system users).</p>
    </div>

    <div class="container-fluid mb-3">
        <form method="GET" action="{{ route('user.index') }}" class="form-inline" style="gap:8px;">
            <input type="hidden" name="category" value="applicants">
            <input type="search" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search applicants…">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <div class="table-responsive mb-4">
        <h5 class="px-3">Portal applicants</h5>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($portalApplicants as $i => $user)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone }}</td>
                        <td><span class="badge badge-info">Applicant</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No portal applicant accounts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="container-fluid mb-2 d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
        <h5 class="mb-0">From applications</h5>
        <div class="d-flex flex-wrap" style="gap:8px;">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="app-select-all">Select all</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="app-clear-all">Clear</button>
            <button type="submit" form="apps-delete-form" class="btn btn-danger btn-sm" id="app-delete-btn" disabled>
                <i class="dripicons-trash"></i> Delete selected
            </button>
        </div>
    </div>

    <form id="apps-delete-form" method="POST" action="{{ route('user.applicants.delete') }}">
        @csrf
        <div class="table-responsive">
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
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($directory as $i => $person)
                        @php
                            $ids = $person['application_ids'] ?? [$person['latest_application_id']];
                            $idsAttr = implode(',', $ids);
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" class="app-row-check" name="application_ids[]" value="{{ $idsAttr }}">
                            </td>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $person['full_name'] }}</td>
                            <td>{{ $person['email'] ?: '—' }}</td>
                            <td>{{ $person['phone'] ?: '—' }}</td>
                            <td>{{ $person['applications_count'] }}</td>
                            <td>{{ str_replace('_', ' ', $person['latest_status']) }}</td>
                            <td>
                                <a href="{{ route('jobs.applications.show', $person['latest_application_id']) }}" class="btn btn-sm btn-link">Open application</a>
                                <button type="button" class="btn btn-sm btn-link text-danger app-delete-one" data-ids="{{ $idsAttr }}">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No applicants found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</section>

<script>
(function () {
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
            alert('Select at least one applicant to delete.');
            return;
        }
        if (!confirm('Delete all applications for the ' + n + ' selected applicant(s)? This cannot be undone.')) {
            e.preventDefault();
        }
    });

    document.querySelectorAll('.app-delete-one').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ids = btn.getAttribute('data-ids') || '';
            if (!ids) return;
            if (!confirm('Delete all applications for this applicant? This cannot be undone.')) return;
            rowChecks().forEach(function (c) { c.checked = false; });
            // Create a temporary checked input for this row's IDs and submit.
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

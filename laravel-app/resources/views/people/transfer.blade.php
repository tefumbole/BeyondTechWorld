@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <h3 style="color:#0b3f90;">People Transfer — Export / Import</h3>
        <p class="text-muted">
            Use these CSVs to move <strong>Users</strong> and <strong>Customers</strong> between Mainmarket and Beyond Enterprise.
            Export from the source site, then Import on the destination. Matching is by <strong>phone_number</strong> (customers) and <strong>email</strong> (users).
        </p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white"><strong>Customers</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">Columns (exact header names):</p>
                        <code style="display:block;white-space:pre-wrap;font-size:12px;background:#f8fafc;padding:10px;border-radius:8px;">{{ implode(', ', $customerHeaders) }}</code>
                        <ul class="small mt-3 mb-3">
                            <li><code>customer_group</code> — group <em>name</em> (e.g. GENERAL). Created group must exist, or GENERAL is used.</li>
                            <li><code>phone_number</code> — primary match key on import.</li>
                            <li><code>is_active</code> — 1 or 0.</li>
                        </ul>
                        <div class="d-flex flex-wrap" style="gap:8px;">
                            <a href="{{ route('people.export.customers') }}" class="btn btn-primary btn-sm"><i class="dripicons-download"></i> Export Customers</a>
                            <a href="{{ route('people.sample.customers') }}" class="btn btn-outline-secondary btn-sm">Sample CSV</a>
                        </div>
                        <hr>
                        <form method="POST" action="{{ route('people.import.customers') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label>Import customers CSV</label>
                                <input type="file" name="file" accept=".csv,text/csv" class="form-control-file" required>
                            </div>
                            <button class="btn btn-success btn-sm"><i class="dripicons-upload"></i> Import Customers</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white"><strong>Users</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">Columns (exact header names):</p>
                        <code style="display:block;white-space:pre-wrap;font-size:12px;background:#f8fafc;padding:10px;border-radius:8px;">{{ implode(', ', $userHeaders) }}</code>
                        <ul class="small mt-3 mb-3">
                            <li><code>email</code> — primary match key on import.</li>
                            <li><code>role_name</code> — Spatie role name (e.g. Admin, Customer). Must exist on destination.</li>
                            <li><code>password</code> — set on create; leave blank on update to keep current password. Blank create uses <code>ChangeMe123!</code>.</li>
                        </ul>
                        <div class="d-flex flex-wrap" style="gap:8px;">
                            <a href="{{ route('people.export.users') }}" class="btn btn-primary btn-sm"><i class="dripicons-download"></i> Export Users</a>
                            <a href="{{ route('people.sample.users') }}" class="btn btn-outline-secondary btn-sm">Sample CSV</a>
                        </div>
                        <hr>
                        <form method="POST" action="{{ route('people.import.users') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label>Import users CSV</label>
                                <input type="file" name="file" accept=".csv,text/csv" class="form-control-file" required>
                            </div>
                            <button class="btn btn-success btn-sm"><i class="dripicons-upload"></i> Import Users</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Recommended flow (Mainmarket → Beyond):</strong>
            <ol class="mb-0 pl-3">
                <li>On Mainmarket open this same page (after deploying) or export CSV with these exact headers.</li>
                <li>Download <em>Export Customers</em> and <em>Export Users</em>.</li>
                <li>On Beyondtechworld → People → Export / Import → upload each file.</li>
                <li>Confirm Customer Groups / Roles with matching names exist on Beyond first.</li>
            </ol>
        </div>
    </div>
</section>
@endsection

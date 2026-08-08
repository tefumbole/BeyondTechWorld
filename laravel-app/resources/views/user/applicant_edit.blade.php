@extends('layout.main')
@section('content')
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<section class="forms">
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center mb-3" style="gap:10px;">
            <a href="{{ route('user.index', ['category' => 'applicants']) }}" class="btn btn-outline-secondary">&larr; Interns</a>
            <a href="{{ route('jobs.applications.show', $application->id) }}" class="btn btn-outline-info">Open application</a>
        </div>
        <h3 style="color:#0b3f90;font-weight:700;">Edit intern &amp; assign role</h3>
        <p class="text-muted">Update contact details and optionally create/link an ERP user with Intern, staff, or supervisor access.</p>

        <div class="card">
            <div class="card-body">
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
                            <label>Warehouse (for Intern / staff)</label>
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
                            <input type="text" name="password" class="form-control" placeholder="Leave blank to auto-generate if new user"
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
                            Linked ERP user: <strong>{{ $linkedUser->name }}</strong> (ID {{ $linkedUser->id }}) —
                            current role ID {{ $linkedUser->role_id }}.
                        </div>
                    @else
                        <div class="alert alert-secondary mb-3">No ERP user linked yet. Choosing a role will create one.</div>
                    @endif

                    <button type="submit" class="btn btn-primary">Save intern</button>
                    <a href="{{ route('user.index', ['category' => 'applicants']) }}" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@extends('layout.main')
@section('content')
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

    <div class="table-responsive">
        <h5 class="px-3">From applications</h5>
        <table class="table">
            <thead>
                <tr>
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
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $person['full_name'] }}</td>
                        <td>{{ $person['email'] ?: '—' }}</td>
                        <td>{{ $person['phone'] ?: '—' }}</td>
                        <td>{{ $person['applications_count'] }}</td>
                        <td>{{ str_replace('_', ' ', $person['latest_status']) }}</td>
                        <td>
                            <a href="{{ route('jobs.applications.show', $person['latest_application_id']) }}" class="btn btn-sm btn-link">Open application</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No applicants found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

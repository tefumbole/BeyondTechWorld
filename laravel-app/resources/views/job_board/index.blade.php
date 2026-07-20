@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="jb-title">Job Board</h1>
                <p class="jb-subtitle">Manage public job and internship postings shown on Apply Now.</p>
            </div>
            <div class="d-flex" style="gap:8px;">
                <a href="{{ route('jobs.create') }}" class="jb-btn"><i class="dripicons-plus"></i> Add Job</a>
                <a href="{{ route('jobs.createInternship') }}" class="jb-btn-secondary"><i class="dripicons-user"></i> Add Internship</a>
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="GET" class="jb-card">
            <div class="row align-items-end">
                <div class="col-md-5 mb-2">
                    <label class="jb-label">Search</label>
                    <input type="search" name="q" value="{{ $q }}" class="jb-field" placeholder="Title, department, location…">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="jb-label">Status</label>
                    <select name="status" class="jb-field">
                        @foreach(['all'=>'All','active'=>'Active','draft'=>'Draft','closed'=>'Closed','archived'=>'Archived'] as $val => $label)
                            <option value="{{ $val }}" @if(($status ?? 'all')===$val) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="jb-btn" style="width:100%;justify-content:center;">Filter</button>
                </div>
            </div>
        </form>

        <div class="jb-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Location</th>
                            <th>Deadline</th>
                            <th>Applicants</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $job)
                            <tr>
                                <td><strong>{{ $job->title }}</strong></td>
                                <td><span class="jb-badge">{{ $job->isInternship() ? 'Internship' : 'Job' }}</span></td>
                                <td>{{ $job->department ?: '—' }}</td>
                                <td>{{ $job->location ?: '—' }}</td>
                                <td>{{ $job->deadline ? \Carbon\Carbon::parse($job->deadline)->format('M j, Y') : '—' }}</td>
                                <td>{{ (int) $job->current_applicants }}</td>
                                <td><span class="jb-badge">{{ $job->status }}</span></td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('jobs.applications', ['job_id' => $job->id]) }}" class="btn btn-sm btn-outline-secondary">Apps</a>
                                    <a href="{{ route('jobs.edit', $job->id) }}" class="btn btn-sm btn-primary" title="Edit"><i class="dripicons-pencil"></i></a>
                                    <form method="POST" action="{{ route('jobs.clone', $job->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-info" title="Clone"><i class="dripicons-duplicate"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('jobs.destroy', $job->id) }}" class="d-inline" onsubmit="return confirm('Delete this posting and its applications?');">
                                        @csrf
                                        <button class="btn btn-sm btn-danger" title="Delete"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No postings found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($items, 'links'))
                <div class="mt-3">{{ $items->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection

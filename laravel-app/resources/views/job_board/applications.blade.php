@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="mb-4">
            <h1 class="jb-title">Applications</h1>
            <p class="jb-subtitle">Review and update candidate applications.</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="GET" class="jb-card">
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="jb-label">Job</label>
                    <select name="job_id" class="jb-field">
                        <option value="all" @if(($jobId ?? 'all')==='all') selected @endif>All Jobs</option>
                        @foreach($jobs as $j)
                            <option value="{{ $j->id }}" @if(($jobId ?? '')==$j->id) selected @endif>{{ $j->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="jb-label">Status</label>
                    <select name="status" class="jb-field">
                        @foreach(['all'=>'All','new'=>'New','reviewed'=>'Reviewed','shortlisted'=>'Shortlisted','interview'=>'Interview','rejected'=>'Rejected','hired'=>'Hired','withdrawn'=>'Withdrawn'] as $val => $label)
                            <option value="{{ $val }}" @if(($status ?? 'all')===$val) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="jb-label">Search</label>
                    <input type="search" name="q" value="{{ $q }}" class="jb-field" placeholder="Name, email, phone, reference…">
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
                            <th>Candidate</th>
                            <th>Job</th>
                            <th>Reference</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>CV</th>
                            <th class="text-right">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $app)
                            <tr>
                                <td>
                                    <strong>{{ $app->full_name }}</strong><br>
                                    <span class="text-muted small">{{ $app->email }} · {{ $app->phone ?: '—' }}</span>
                                </td>
                                <td>{{ optional($app->job)->title ?: '—' }}</td>
                                <td><code>{{ $app->reference_number }}</code></td>
                                <td>{{ $app->submitted_at ? \Carbon\Carbon::parse($app->submitted_at)->format('M j, Y') : '—' }}</td>
                                <td><span class="jb-badge">{{ $app->status }}</span></td>
                                <td>
                                    @if($app->cv_url)
                                        <a href="{{ $app->cv_url }}" target="_blank" rel="noopener">View</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('jobs.applications.update', $app->id) }}" class="d-inline-flex align-items-center" style="gap:6px;">
                                        @csrf
                                        <select name="status" class="jb-field" style="width:auto;min-width:120px;">
                                            @foreach(['new','reviewed','shortlisted','interview','rejected','hired','withdrawn'] as $st)
                                                <option value="{{ $st }}" @if($app->status===$st) selected @endif>{{ ucfirst($st) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No applications found.</td></tr>
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

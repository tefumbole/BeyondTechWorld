@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="mb-4">
            <h1 class="jb-title">{{ $pageTitle ?? 'Applications' }}</h1>
            <p class="jb-subtitle">Review candidates. Status changes notify them on WhatsApp. Selecting a candidate sends the agreement link.</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="GET" class="jb-card">
            <div class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="jb-label">Job / Internship</label>
                    <select name="job_id" class="jb-field">
                        <option value="all" @if(($jobId ?? 'all')==='all') selected @endif>All</option>
                        @foreach($jobs as $j)
                            <option value="{{ $j->id }}" @if(($jobId ?? '')==$j->id) selected @endif>
                                {{ $j->title }} {{ ($j->posting_type ?? '') === 'internship' ? '(Internship)' : '(Job)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="jb-label">Search</label>
                    <input type="search" name="q" value="{{ $q }}" class="jb-field" placeholder="Name, email, WhatsApp, reference…">
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
                            <th>Role</th>
                            <th>Reference</th>
                            <th>Submitted</th>
                            <th>Docs</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $app)
                            <tr>
                                <td>
                                    <strong>{{ $app->full_name }}</strong><br>
                                    <span class="text-muted small">{{ $app->email }}</span><br>
                                    <span class="text-muted small">WA: {{ $app->whatsapp_number ?: $app->phone ?: '—' }}</span>
                                </td>
                                <td>
                                    {{ optional($app->job)->title ?: '—' }}
                                    @if(optional($app->job)->isInternship())
                                        <br><span class="jb-badge">Internship</span>
                                    @endif
                                </td>
                                <td><code>{{ $app->reference_number }}</code></td>
                                <td>{{ $app->submitted_at ? \Carbon\Carbon::parse($app->submitted_at)->format('M j, Y') : '—' }}</td>
                                <td class="small">
                                    @if($app->cv_url)<a href="{{ $app->cv_url }}" target="_blank" rel="noopener">CV</a>@endif
                                    @if($app->student_id_path)<br><a href="{{ url($app->student_id_path) }}" target="_blank" rel="noopener">Student ID</a>@endif
                                    @if($app->internship_letter_path)<br><a href="{{ url($app->internship_letter_path) }}" target="_blank" rel="noopener">Letter</a>@endif
                                    @if($app->selfie_path)<br><a href="{{ url($app->selfie_path) }}" target="_blank" rel="noopener">Selfie</a>@endif
                                    @if($app->agreement_signed_at)<br><span class="text-success">Agreement signed</span>@endif
                                </td>
                                <td><span class="jb-badge">{{ $app->statusLabel() }}</span></td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('jobs.applications.update', $app->id) }}" class="d-inline-flex flex-column align-items-end" style="gap:6px;min-width:180px;">
                                        @csrf
                                        <select name="status" class="jb-field" style="width:100%;">
                                            @foreach([
                                                'awaiting_approval' => 'Awaiting Approval',
                                                'selected' => 'Selected',
                                                'rejected' => 'Rejected',
                                                'hired' => 'Hired',
                                            ] as $st => $label)
                                                <option value="{{ $st }}" @if(in_array($app->status, [$st], true) || ($st==='awaiting_approval' && in_array($app->status, ['new','reviewed','interview'], true)) || ($st==='selected' && $app->status==='shortlisted')) selected @endif>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="rejection_reason" class="jb-field" placeholder="Rejection reason (optional)" value="{{ $app->rejection_reason }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Save & Notify</button>
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

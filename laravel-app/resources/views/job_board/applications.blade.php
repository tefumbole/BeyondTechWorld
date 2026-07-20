@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="mb-4">
            <h1 class="jb-title">{{ $pageTitle ?? 'Applications' }}</h1>
            <p class="jb-subtitle">Click a row to open the full application (documents, ID, selfie, signature). Status changes notify candidates on WhatsApp.</p>
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
                @if(!empty($showStatusFilter))
                    <div class="col-md-3 mb-2">
                        <label class="jb-label">Status</label>
                        <select name="status" class="jb-field">
                            @foreach([
                                'all' => 'All',
                                'awaiting_approval' => 'Awaiting Approval',
                                'selected' => 'Selected',
                                'rejected' => 'Rejected',
                                'hired' => 'Hired',
                            ] as $val => $label)
                                <option value="{{ $val }}" @if(($status ?? 'all')===$val) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-{{ !empty($showStatusFilter) ? '3' : '4' }} mb-2">
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
                            <th>Student Name</th>
                            <th>Contact</th>
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
                            <tr class="jb-row-click" data-href="{{ route('jobs.applications.show', $app->id) }}">
                                <td>
                                    <strong>{{ $app->full_name }}</strong>
                                </td>
                                <td>
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
                                <td class="small jb-docs" onclick="event.stopPropagation();">
                                    @if($app->cv_url || $app->cv_path)
                                        <a href="{{ route('jobs.applications.document', [$app->id, 'cv']) }}" target="_blank" rel="noopener">CV</a>
                                    @endif
                                    @if($app->student_id_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'student_id']) }}" target="_blank" rel="noopener">Student ID</a>
                                    @endif
                                    @if($app->internship_letter_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" target="_blank" rel="noopener">Letter</a>
                                    @endif
                                    @if($app->selfie_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'selfie']) }}" target="_blank" rel="noopener">Selfie</a>
                                    @endif
                                    @if($app->agreement_signed_at)<br><span class="text-success">Agreement signed</span>@endif
                                    @if(!$app->cv_url && !$app->cv_path && !$app->student_id_path && !$app->internship_letter_path && !$app->selfie_path)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="jb-badge">{{ $app->statusLabel() }}</span></td>
                                <td class="text-right" onclick="event.stopPropagation();">
                                    <form method="POST" action="{{ route('jobs.applications.update', $app->id) }}" class="jb-status-form d-inline-flex flex-column align-items-end" style="gap:6px;min-width:200px;">
                                        @csrf
                                        <select name="status" class="jb-field jb-status-select" style="width:100%;">
                                            @foreach([
                                                'awaiting_approval' => 'Awaiting Approval',
                                                'selected' => 'Selected',
                                                'rejected' => 'Rejected',
                                                'hired' => 'Hired',
                                            ] as $st => $label)
                                                <option value="{{ $st }}" @if(in_array($app->status, [$st], true) || ($st==='awaiting_approval' && in_array($app->status, ['new','reviewed','interview'], true)) || ($st==='selected' && $app->status==='shortlisted') || ($st==='hired' && $app->status==='hired')) selected @endif>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="status_reason" class="jb-field jb-reason-input" data-current="{{ $app->status }}" placeholder="Reason (optional)" value="{{ $app->rejection_reason }}">
                                        <a href="{{ route('jobs.applications.show', $app->id) }}" class="btn btn-sm btn-outline-primary" style="width:100%;">View Application</a>
                                        <button type="submit" class="btn btn-sm btn-primary" style="width:100%;">Save & Notify</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No applications found.</td></tr>
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

@section('scripts')
<script>
(function () {
    function reasonPlaceholder(status) {
        if (status === 'selected') return 'Selection reason (optional)';
        if (status === 'hired') return 'Hired reason (optional)';
        if (status === 'rejected') return 'Rejection reason (optional)';
        return 'Note / reason (optional)';
    }
    function syncReason($form) {
        var status = $form.find('.jb-status-select').val();
        $form.find('.jb-reason-input').attr('placeholder', reasonPlaceholder(status));
    }
    $(document).on('change', '.jb-status-select', function () {
        syncReason($(this).closest('.jb-status-form'));
    });
    $('.jb-status-form').each(function () { syncReason($(this)); });

    $(document).on('click', 'tr.jb-row-click', function (e) {
        if ($(e.target).closest('a, button, form, input, select, textarea, .jb-docs').length) return;
        var href = $(this).data('href');
        if (href) window.location.href = href;
    });
})();
</script>
@endsection

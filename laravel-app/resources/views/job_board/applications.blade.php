@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="mb-4">
            <h1 class="jb-title">{{ $pageTitle ?? 'Applications' }}</h1>
            <p class="jb-subtitle">Click anywhere on a row (except Actions / Docs) to open the full application — including education, documents, signatures, and status history. Status changes notify candidates on WhatsApp.</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
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
                    <input type="search" name="q" value="{{ $q }}" class="jb-field" placeholder="Name, email, WhatsApp, school, reference…">
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="jb-btn" style="width:100%;justify-content:center;">Filter</button>
                </div>
            </div>
        </form>

        <div class="jb-card jb-apps-card">
            <div class="table-responsive jb-apps-table-wrap">
                <table class="table mb-0 jb-apps-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Contact</th>
                            <th>Education</th>
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
                            @php $showUrl = route('jobs.applications.show', $app->id); @endphp
                            <tr class="jb-row-click" data-href="{{ $showUrl }}">
                                <td class="jb-nav-cell">
                                    <strong>{{ $app->full_name }}</strong>
                                </td>
                                <td class="jb-nav-cell">
                                    <span class="text-muted small">{{ $app->email }}</span><br>
                                    <span class="text-muted small">WA: {{ $app->whatsapp_number ?: $app->phone ?: '—' }}</span>
                                </td>
                                <td class="jb-nav-cell small">
                                    @if($app->school || $app->level_of_study || $app->education_status || $app->is_academic_required !== null)
                                        <strong>{{ $app->school ?: '—' }}</strong><br>
                                        <span class="text-muted">{{ $app->level_of_study ?: '—' }}</span><br>
                                        <span>{{ $app->educationStatusLabel() }}</span><br>
                                        <span class="jb-badge">{{ $app->academicRequiredLabel() }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="jb-nav-cell">
                                    {{ optional($app->job)->title ?: '—' }}
                                    @if(optional($app->job)->isInternship())
                                        <br><span class="jb-badge">Internship</span>
                                    @endif
                                </td>
                                <td class="jb-nav-cell"><code>{{ $app->reference_number }}</code></td>
                                <td class="jb-nav-cell">{{ $app->submitted_at ? \Carbon\Carbon::parse($app->submitted_at)->format('M j, Y') : '—' }}</td>
                                <td class="small jb-no-nav">
                                    @if($app->cv_url || $app->cv_path)
                                        <a href="{{ route('jobs.applications.document', [$app->id, 'cv']) }}" target="_blank" rel="noopener">CV</a>
                                    @endif
                                    @if($app->student_id_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'student_id']) }}" target="_blank" rel="noopener">ID Front</a>
                                    @endif
                                    @if($app->student_id_back_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'student_id_back']) }}" target="_blank" rel="noopener">ID Back</a>
                                    @endif
                                    @if($app->internship_letter_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" target="_blank" rel="noopener">Letter</a>
                                    @endif
                                    @if($app->selfie_path)
                                        <br><a href="{{ route('jobs.applications.document', [$app->id, 'selfie']) }}" target="_blank" rel="noopener">Selfie</a>
                                    @endif
                                    @if($app->agreement_signed_at)<br><span class="text-success">Agreement signed</span>@endif
                                    @if(!$app->cv_url && !$app->cv_path && !$app->student_id_path && !$app->student_id_back_path && !$app->internship_letter_path && !$app->selfie_path)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="jb-nav-cell"><span class="jb-badge">{{ $app->statusLabel() }}</span></td>
                                <td class="text-right jb-no-nav jb-actions-cell">
                                    <form method="POST" action="{{ route('jobs.applications.update', $app->id) }}" class="jb-status-form" style="display:flex;flex-direction:column;align-items:stretch;gap:6px;min-width:210px;">
                                        @csrf
                                        <select name="status" class="jb-native-select jb-status-select" autocomplete="off">
                                            @foreach([
                                                'awaiting_approval' => 'Awaiting Approval',
                                                'selected' => 'Selected (send agreement)',
                                                'rejected' => 'Rejected',
                                                'hired' => 'Hired (only if agreement signed)',
                                            ] as $st => $label)
                                                <option value="{{ $st }}" @if(in_array($app->status, [$st], true) || ($st==='awaiting_approval' && in_array($app->status, ['new','reviewed','interview'], true)) || ($st==='selected' && $app->status==='shortlisted')) selected @endif>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="status_reason" class="jb-field jb-reason-input" placeholder="Note / reason (optional)" value="{{ $app->rejection_reason }}">
                                        <button type="submit" class="btn btn-sm btn-primary">Save &amp; Notify</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No applications found.</td></tr>
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
<style>
    .jb-apps-card { overflow: visible; }
    .jb-apps-table-wrap { overflow: visible !important; }
    .jb-apps-table { position: relative; }
    tr.jb-row-click td.jb-nav-cell { cursor: pointer; }
    tr.jb-row-click:hover td.jb-nav-cell { background: #f8fafc; }
    td.jb-no-nav, td.jb-actions-cell { cursor: default; position: relative; z-index: 20; }
    .jb-native-select {
        display: block;
        width: 100%;
        min-height: 38px;
        padding: 8px 10px;
        border: 1px solid #0b3f90;
        border-radius: 8px;
        background: #fff;
        color: #0b3f90;
        font-weight: 600;
        font-size: 13px;
        appearance: auto;
        -webkit-appearance: menulist;
        -moz-appearance: menulist;
        pointer-events: auto !important;
        position: relative;
        z-index: 30;
    }
    .jb-actions-cell .jb-field,
    .jb-actions-cell .btn {
        pointer-events: auto !important;
        position: relative;
        z-index: 30;
    }
    /* Prevent bootstrap-select / theme from hijacking these */
    .jb-native-select.bootstrap-select,
    .jb-actions-cell .bootstrap-select { display: none !important; }
</style>
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

    // Destroy bootstrap-select if theme auto-wrapped these selects
    if ($.fn.selectpicker) {
        $('.jb-status-select').each(function () {
            var $el = $(this);
            if ($el.parent().hasClass('bootstrap-select') || $el.data('selectpicker')) {
                try { $el.selectpicker('destroy'); } catch (e) {}
            }
            $el.removeClass('selectpicker form-control');
        });
    }

    $(document).on('mousedown click touchstart change', '.jb-actions-cell, .jb-status-form, .jb-native-select, .jb-reason-input', function (e) {
        e.stopPropagation();
    });

    $(document).on('change', '.jb-status-select', function () {
        syncReason($(this).closest('.jb-status-form'));
    });
    $('.jb-status-form').each(function () { syncReason($(this)); });

    $(document).on('click', 'tr.jb-row-click td.jb-nav-cell', function () {
        var href = $(this).closest('tr').data('href');
        if (href) window.location.href = href;
    });
})();
</script>
@endsection

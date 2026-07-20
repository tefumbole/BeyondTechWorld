@extends('layout.main')

@section('content')
@php
    $job = $app->job;
    $isInternship = $job && method_exists($job, 'isInternship') ? $job->isInternship() : false;
@endphp
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="jb-title">Application Details</h1>
                <p class="jb-subtitle">Full submission for <strong>{{ $app->full_name }}</strong> · {{ $app->reference_number }}</p>
            </div>
            <div class="d-flex" style="gap:8px;">
                <a href="{{ url()->previous() ?: route('jobs.applications') }}" class="jb-btn-secondary"><i class="dripicons-arrow-left"></i> Back</a>
                <a href="{{ route('jobs.awaiting') }}" class="jb-btn-secondary">Awaiting</a>
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-7">
                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Student / Candidate</h5>
                    <table class="table table-sm mb-0">
                        <tr><th style="width:160px;">Student Name</th><td><strong>{{ $app->full_name }}</strong></td></tr>
                        <tr><th>Email</th><td>{{ $app->email ?: '—' }}</td></tr>
                        <tr><th>Phone</th><td>{{ $app->phone ?: '—' }}</td></tr>
                        <tr><th>WhatsApp</th><td>{{ $app->whatsapp_number ?: '—' }}</td></tr>
                        <tr><th>Country</th><td>{{ $app->country ?: '—' }}</td></tr>
                        <tr><th>Reference</th><td><code>{{ $app->reference_number }}</code></td></tr>
                        <tr><th>Status</th><td><span class="jb-badge">{{ $app->statusLabel() }}</span></td></tr>
                        <tr><th>Submitted</th><td>{{ $app->submitted_at ? \Carbon\Carbon::parse($app->submitted_at)->format('M j, Y H:i') : '—' }}</td></tr>
                        <tr><th>Availability</th><td>{{ $app->availability ?: ($app->availability_days ?: '—') }}</td></tr>
                        @if($app->expected_salary)
                            <tr><th>Expected salary</th><td>{{ $app->expected_salary }}</td></tr>
                        @endif
                    </table>
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Role applied for</h5>
                    <p class="mb-1"><strong>{{ optional($job)->title ?: '—' }}</strong>
                        @if($isInternship) <span class="jb-badge">Internship</span>@endif
                    </p>
                    <p class="text-muted small mb-0">
                        {{ optional($job)->department ?: '' }}
                        @if(optional($job)->location) · {{ $job->location }}@endif
                    </p>
                </div>

                @if($app->cover_letter)
                    <div class="jb-card">
                        <h5 style="color:#0b3f90;font-weight:800;">Cover letter / motivation</h5>
                        <div style="white-space:pre-wrap;">{{ $app->cover_letter }}</div>
                    </div>
                @endif

                @if($app->signature_image)
                    <div class="jb-card">
                        <h5 style="color:#0b3f90;font-weight:800;">Application signature</h5>
                        <div class="jb-sig-box">
                            <img src="{{ $app->signature_image }}" alt="Signature">
                        </div>
                    </div>
                @endif

                @if($app->agreement_signature_image)
                    <div class="jb-card">
                        <h5 style="color:#0b3f90;font-weight:800;">Agreement signature</h5>
                        <p class="small text-muted">Signed {{ $app->agreement_signed_at ? $app->agreement_signed_at->format('M j, Y H:i') : '' }}</p>
                        <div class="jb-sig-box">
                            <img src="{{ $app->agreement_signature_image }}" alt="Agreement signature">
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Documents</h5>
                    <div class="d-flex flex-column" style="gap:14px;">
                        @if($app->cv_url || $app->cv_path)
                            <div>
                                <a class="jb-btn" href="{{ route('jobs.applications.document', [$app->id, 'cv']) }}" target="_blank" rel="noopener">
                                    <i class="dripicons-document"></i> Open CV
                                </a>
                            </div>
                        @endif
                        @if($app->student_id_path)
                            <div>
                                <div class="jb-label">ID card — Front</div>
                                <a href="{{ route('jobs.applications.document', [$app->id, 'student_id']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'student_id']) }}" alt="ID Front">
                                </a>
                            </div>
                        @endif
                        @if($app->student_id_back_path)
                            <div>
                                <div class="jb-label">ID card — Back</div>
                                <a href="{{ route('jobs.applications.document', [$app->id, 'student_id_back']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'student_id_back']) }}" alt="ID Back">
                                </a>
                            </div>
                        @endif
                        @if($app->internship_letter_path)
                            <div>
                                <div class="jb-label">Internship Letter</div>
                                <a href="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" alt="Letter"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                </a>
                                <a class="jb-btn-secondary mt-2" style="display:none;" href="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" target="_blank" rel="noopener">Open letter file</a>
                            </div>
                        @endif
                        @if($app->selfie_path)
                            <div>
                                <div class="jb-label">Selfie</div>
                                <a href="{{ route('jobs.applications.document', [$app->id, 'selfie']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'selfie']) }}" alt="Selfie">
                                </a>
                            </div>
                        @endif
                        @if(!$app->cv_url && !$app->cv_path && !$app->student_id_path && !$app->student_id_back_path && !$app->internship_letter_path && !$app->selfie_path)
                            <p class="text-muted mb-0">No documents uploaded.</p>
                        @endif
                    </div>
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Update status</h5>
                    <form method="POST" action="{{ route('jobs.applications.update', $app->id) }}" class="jb-status-form" style="display:flex;flex-direction:column;gap:10px;">
                        @csrf
                        <div>
                            <label class="jb-label">Status</label>
                            <select name="status" class="jb-field jb-status-select">
                                @foreach([
                                    'awaiting_approval' => 'Awaiting Approval',
                                    'selected' => 'Selected',
                                    'rejected' => 'Rejected',
                                    'hired' => 'Hired',
                                ] as $st => $label)
                                    <option value="{{ $st }}" @if($app->status === $st || ($st==='awaiting_approval' && in_array($app->status, ['new','reviewed','interview'], true)) || ($st==='selected' && $app->status==='shortlisted')) selected @endif>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="jb-label jb-reason-label">Reason</label>
                            <input type="text" name="status_reason" class="jb-field jb-reason-input" value="{{ $app->rejection_reason }}" placeholder="Reason (optional)">
                        </div>
                        <button type="submit" class="jb-btn" style="justify-content:center;">Save & Notify</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    function reasonMeta(status) {
        if (status === 'selected') return { label: 'Selection reason', ph: 'Selection reason (optional)' };
        if (status === 'hired') return { label: 'Hired reason', ph: 'Hired reason (optional)' };
        if (status === 'rejected') return { label: 'Rejection reason', ph: 'Rejection reason (optional)' };
        return { label: 'Note / reason', ph: 'Note (optional)' };
    }
    function sync($form) {
        var status = $form.find('.jb-status-select').val();
        var meta = reasonMeta(status);
        $form.find('.jb-reason-label').text(meta.label);
        $form.find('.jb-reason-input').attr('placeholder', meta.ph);
    }
    $(document).on('change', '.jb-status-select', function () {
        sync($(this).closest('.jb-status-form'));
    });
    $('.jb-status-form').each(function () { sync($(this)); });
})();
</script>
@endsection

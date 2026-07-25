@extends('layout.main')

@section('content')
@php
    $job = $app->job;
    $isInternship = $job && method_exists($job, 'isInternship') ? $job->isInternship() : false;
    $availabilityDisplay = $app->availability ?: '—';
    if (($app->availability === 'Custom' || $app->availability === 'custom') && $app->availability_days) {
        $availabilityDisplay = $app->availability_days.' day(s)';
    }
@endphp
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="jb-title">Application Details</h1>
                <p class="jb-subtitle">Complete submission for <strong>{{ $app->full_name }}</strong> · {{ $app->reference_number }}</p>
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
                    <h5 style="color:#0b3f90;font-weight:800;">Candidate</h5>
                    <table class="table table-sm mb-0 jb-detail-table">
                        <tr><th>Student Name</th><td><strong>{{ $app->full_name ?: '—' }}</strong></td></tr>
                        <tr><th>Email</th><td>{{ $app->email ?: '—' }}</td></tr>
                        <tr><th>Phone</th><td>{{ $app->phone ?: '—' }}</td></tr>
                        <tr><th>WhatsApp</th><td>{{ $app->whatsapp_number ?: '—' }}</td></tr>
                        <tr><th>Country</th><td>{{ $app->country ?: '—' }}</td></tr>
                        <tr><th>Reference</th><td><code>{{ $app->reference_number ?: '—' }}</code></td></tr>
                        <tr><th>Status</th><td><span class="jb-badge">{{ $app->statusLabel() }}</span></td></tr>
                        <tr><th>Status note / reason</th><td>{{ $app->rejection_reason ?: '—' }}</td></tr>
                        <tr><th>Submitted</th><td>{{ $app->submitted_at ? \Carbon\Carbon::parse($app->submitted_at)->format('M j, Y H:i') : '—' }}</td></tr>
                        <tr><th>Availability</th><td>{{ $availabilityDisplay }}</td></tr>
                        <tr><th>Expected salary</th><td>{{ $app->expected_salary ?: '—' }}</td></tr>
                        @if($app->interview_date)
                            <tr><th>Interview date</th><td>{{ \Carbon\Carbon::parse($app->interview_date)->format('M j, Y') }}</td></tr>
                        @endif
                    </table>
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Education</h5>
                    @if($isInternship || $app->school || $app->level_of_study || $app->education_status || $app->is_academic_required !== null)
                        <table class="table table-sm mb-0 jb-detail-table">
                            <tr><th>School / Institution</th><td>{{ $app->school ?: '—' }}</td></tr>
                            <tr><th>Level of study</th><td>{{ $app->level_of_study ?: '—' }}</td></tr>
                            <tr><th>Student / Graduated</th><td>{{ $app->educationStatusLabel() }}</td></tr>
                            <tr><th>Academic-required internship</th><td>{{ $app->academicRequiredLabel() }}</td></tr>
                        </table>
                    @else
                        <p class="text-muted mb-0 small">No education fields on this application (older submission or job posting).</p>
                    @endif
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Role applied for</h5>
                    <table class="table table-sm mb-0 jb-detail-table">
                        <tr><th>Title</th>
                            <td>
                                <strong>{{ optional($job)->title ?: '—' }}</strong>
                                @if($isInternship) <span class="jb-badge">Internship</span>
                                @elseif($job) <span class="jb-badge">Job</span>@endif
                            </td>
                        </tr>
                        <tr><th>Department</th><td>{{ optional($job)->department ?: '—' }}</td></tr>
                        <tr><th>Location</th><td>{{ optional($job)->location ?: '—' }}</td></tr>
                        <tr><th>Posting type</th><td>{{ optional($job)->posting_type ?: '—' }}</td></tr>
                    </table>
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Cover letter / motivation</h5>
                    @if($app->cover_letter)
                        <div style="white-space:pre-wrap;">{{ $app->cover_letter }}</div>
                    @else
                        <p class="text-muted mb-0">—</p>
                    @endif
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Agreement &amp; signatures</h5>
                    <table class="table table-sm mb-0 jb-detail-table">
                        <tr><th>Agreement sent</th><td>{{ $app->agreement_sent_at ? \Carbon\Carbon::parse($app->agreement_sent_at)->format('M j, Y H:i') : '—' }}</td></tr>
                        <tr><th>Agreement signed</th><td>{{ $app->agreement_signed_at ? \Carbon\Carbon::parse($app->agreement_signed_at)->format('M j, Y H:i') : '—' }}</td></tr>
                    </table>
                    @if($app->signature_image)
                        <div class="mt-3">
                            <div class="jb-label">Application signature</div>
                            <div class="jb-sig-box">
                                <img src="{{ $app->signature_image }}" alt="Application signature">
                            </div>
                        </div>
                    @else
                        <p class="text-muted small mt-3 mb-0">Application signature: —</p>
                    @endif
                    @if($app->agreement_signature_image)
                        <div class="mt-3">
                            <div class="jb-label">Agreement signature</div>
                            <div class="jb-sig-box">
                                <img src="{{ $app->agreement_signature_image }}" alt="Agreement signature">
                            </div>
                        </div>
                    @else
                        <p class="text-muted small mt-2 mb-0">Agreement signature: —</p>
                    @endif
                </div>
            </div>

            <div class="col-lg-5">
                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Documents</h5>
                    <div class="d-flex flex-column" style="gap:14px;">
                        <div>
                            <div class="jb-label">Resume / CV</div>
                            @if($app->cv_url || $app->cv_path)
                                <a class="jb-btn" href="{{ route('jobs.applications.document', [$app->id, 'cv']) }}" target="_blank" rel="noopener">
                                    <i class="dripicons-document"></i> Open CV
                                </a>
                            @else
                                <p class="text-muted small mb-0">—</p>
                            @endif
                        </div>
                        <div>
                            <div class="jb-label">ID card — Front</div>
                            @if($app->student_id_path)
                                <a href="{{ route('jobs.applications.document', [$app->id, 'student_id']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'student_id']) }}" alt="ID Front">
                                </a>
                            @else
                                <p class="text-muted small mb-0">—</p>
                            @endif
                        </div>
                        <div>
                            <div class="jb-label">ID card — Back</div>
                            @if($app->student_id_back_path)
                                <a href="{{ route('jobs.applications.document', [$app->id, 'student_id_back']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'student_id_back']) }}" alt="ID Back">
                                </a>
                            @else
                                <p class="text-muted small mb-0">—</p>
                            @endif
                        </div>
                        <div>
                            <div class="jb-label">Internship letter</div>
                            @if($app->internship_letter_path)
                                <a href="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" alt="Letter"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                </a>
                                <a class="jb-btn-secondary mt-2" style="display:none;" href="{{ route('jobs.applications.document', [$app->id, 'letter']) }}" target="_blank" rel="noopener">Open letter file</a>
                            @else
                                <p class="text-muted small mb-0">—</p>
                            @endif
                        </div>
                        <div>
                            <div class="jb-label">Selfie / Photo</div>
                            @if($app->selfie_path)
                                <a href="{{ route('jobs.applications.document', [$app->id, 'selfie']) }}" target="_blank" rel="noopener">
                                    <img class="jb-doc-thumb" src="{{ route('jobs.applications.document', [$app->id, 'selfie']) }}" alt="Selfie">
                                </a>
                            @else
                                <p class="text-muted small mb-0">—</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="jb-card">
                    <h5 style="color:#0b3f90;font-weight:800;">Update status</h5>
                    <form method="POST" action="{{ route('jobs.applications.update', $app->id) }}" class="jb-status-form" style="display:flex;flex-direction:column;gap:10px;">
                        @csrf
                        <div>
                            <label class="jb-label">Status</label>
                            <select name="status" class="jb-native-select jb-status-select" autocomplete="off">
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
<style>
    .jb-detail-table th { width: 200px; color: #475569; font-weight: 600; vertical-align: top; }
    .jb-detail-table td { word-break: break-word; }
    .jb-native-select {
        display:block;width:100%;min-height:38px;padding:8px 10px;
        border:1px solid #0b3f90;border-radius:8px;background:#fff;color:#0b3f90;
        font-weight:600;appearance:auto;-webkit-appearance:menulist;
    }
</style>
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

@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php
    $task = $assignment->task;
    $progress = $stepProgress ?? $assignment->stepProgress();
    $checked = $progress['checked'] ?? [];
    $steps = $progress['steps'] ?? $task->instructions();
    $canEditSteps = in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true);
@endphp
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.student.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Task #{{ $assignment->progression_day }} — {{ $task->title }}</h1>
        <p class="ip-meta">{{ optional($assignment->enrolment->program)->name }} · {{ str_replace('_',' ', $assignment->status) }}</p>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="ip-card ip-pending">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <div>
                    <div class="ip-meta">Guide checklist progress</div>
                    <strong id="ip-step-count" style="font-size:1.25rem;color:#0b3f90;">{{ $progress['done'] }}/{{ $progress['total'] }}</strong>
                    <span class="ip-meta"> steps complete</span>
                </div>
                <strong id="ip-step-pct" style="color:#0b3f90;">{{ $progress['percent'] }}%</strong>
            </div>
            <div class="ip-progress-wrap mt-2 mb-0">
                <div class="ip-progress-bar ip-progress-bar-lg"><span id="ip-step-bar" style="width:{{ $progress['percent'] }}%;"></span></div>
            </div>
        </div>

        <div class="ip-card">
            <h5 style="font-weight:700;color:#0b3f90;">Objective</h5>
            <p>{{ $task->objective }}</p>
            <p class="ip-meta">Tools: {{ $task->tools ?: '—' }} · Est. {{ $task->estimated_hours }}h · Pass mark {{ $task->pass_mark }}/100</p>

            @if(!empty($hasHandbook))
                <div class="mb-3">
                    <a class="ip-btn" href="{{ route('internship.student.handbook', $assignment->id) }}">
                        <i class="dripicons-download"></i> Download Day {{ $task->day_number }} Student Handbook (DOCX)
                    </a>
                    <p class="ip-meta mb-0 mt-2">Full step-by-step lab guide for this day (install/verify, practical steps, troubleshooting, evidence, and report template).</p>
                </div>
            @endif

            <h5 style="font-weight:700;color:#0b3f90;">Study Notes</h5>
            @if($task->study_note)
                <div class="ip-study-note">{{ $task->study_note }}</div>
            @else
                <p class="text-muted">Study notes are not loaded for this day. Download the Student Handbook above, or contact your supervisor.</p>
            @endif

            <h5 style="font-weight:700;color:#0b3f90;">Guide checklist</h5>
            <p class="ip-meta mb-2">Tick each point as you complete it. Progress is saved automatically and visible in the Internship Task Manager.</p>
            @if(!empty($steps))
                <form id="ip-steps-form" method="POST" action="{{ route('internship.student.steps', $assignment->id) }}">
                    @csrf
                    <ul class="ip-checklist">
                        @foreach($steps as $idx => $line)
                            @php
                                $label = is_string($line) ? $line : json_encode($line);
                                $isChecked = in_array((int) $idx, $checked, true);
                            @endphp
                            <li class="ip-check-item {{ $isChecked ? 'is-done' : '' }}">
                                <label>
                                    <input type="checkbox"
                                           class="ip-step-check"
                                           name="checked[]"
                                           value="{{ $idx }}"
                                           @if($isChecked) checked @endif
                                           @if(!$canEditSteps) disabled @endif>
                                    <span class="ip-check-num">{{ $idx + 1 }}</span>
                                    <span class="ip-check-text">{{ $label }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    @if($canEditSteps)
                        <noscript>
                            <button class="ip-btn mt-2" type="submit">Save checklist</button>
                        </noscript>
                    @endif
                </form>
            @else
                <p class="text-muted">No step instructions are loaded for this day. Use the Student Handbook procedure, then ask your supervisor.</p>
            @endif

            @if($task->submission_requirements)
                <h5 style="font-weight:700;color:#0b3f90;">Submission requirements</h5>
                <p>{{ $task->submission_requirements }}</p>
            @endif
        </div>

        @if(in_array($assignment->status, ['available', 'revision_required'], true))
            <form method="POST" action="{{ route('internship.student.start', $assignment->id) }}" class="mb-3">
                @csrf
                <button class="ip-btn" type="submit"><i class="dripicons-media-play"></i> Start / Continue task</button>
            </form>
        @endif

        @if(in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true))
            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Submit evidence</h5>
                <p class="ip-meta">Upload screenshots and/or PDF (max 10 files, 10MB each). Your supervisor will be notified on WhatsApp.</p>
                <form method="POST" action="{{ route('internship.student.submit', $assignment->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-control" rows="6" required minlength="20" placeholder="Describe what you did, how you verified it, and any issues you solved.">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Screenshots / PDF *</label>
                        <input type="file" name="files[]" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" multiple required>
                    </div>
                    <button class="ip-btn" type="submit"><i class="dripicons-cloud-upload"></i> Submit for grading</button>
                </form>
            </div>
        @endif

        @if($assignment->submissions->count())
            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Attempt history</h5>
                @foreach($assignment->submissions as $sub)
                    <div class="mb-3 pb-3 border-bottom">
                        <strong>Attempt #{{ $sub->attempt_no }}</strong>
                        <span class="ip-badge">{{ str_replace('_',' ', $sub->status) }}</span>
                        <div class="ip-meta">{{ optional($sub->submitted_at)->format('d M Y H:i') }}</div>
                        <p class="mt-2 mb-1">{{ $sub->description }}</p>
                        <div>
                            @foreach($sub->files as $f)
                                <a href="{{ route('internship.student.file', $f->id) }}" target="_blank">{{ $f->original_name }}</a>@if(!$loop->last), @endif
                            @endforeach
                        </div>
                        @foreach($sub->grades as $g)
                            <div class="mt-2 p-2" style="background:#f8fafc;border-radius:8px;">
                                <strong>Grade:</strong> {{ $g->score }}/100 — {{ str_replace('_',' ', $g->decision) }}
                                @if($g->auto_accepted)<span class="ip-badge warn">Auto-accepted</span>@endif
                                @if($g->feedback)<div class="mt-1">{{ $g->feedback }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@if($canEditSteps)
<script>
(function () {
    var form = document.getElementById('ip-steps-form');
    if (!form) return;
    var token = form.querySelector('input[name="_token"]').value;
    var saveTimer = null;

    function collectChecked() {
        return Array.prototype.map.call(form.querySelectorAll('.ip-step-check:checked'), function (el) {
            return parseInt(el.value, 10);
        });
    }

    function updateUi(progress) {
        var bar = document.getElementById('ip-step-bar');
        var count = document.getElementById('ip-step-count');
        var pct = document.getElementById('ip-step-pct');
        if (bar) bar.style.width = (progress.percent || 0) + '%';
        if (count) count.textContent = (progress.done || 0) + '/' + (progress.total || 0);
        if (pct) pct.textContent = (progress.percent || 0) + '%';
        Array.prototype.forEach.call(form.querySelectorAll('.ip-check-item'), function (li) {
            var cb = li.querySelector('.ip-step-check');
            li.classList.toggle('is-done', !!(cb && cb.checked));
        });
    }

    function saveSteps() {
        var body = new FormData();
        body.append('_token', token);
        collectChecked().forEach(function (i) { body.append('checked[]', i); });
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.progress) updateUi(data.progress);
        }).catch(function () {});
    }

    form.addEventListener('change', function (e) {
        if (!e.target.classList.contains('ip-step-check')) return;
        e.target.closest('.ip-check-item').classList.toggle('is-done', e.target.checked);
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveSteps, 250);
    });
})();
</script>
@endif
@endsection

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
        <p class="ip-meta">{{ optional($assignment->enrolment->program)->displayName() ?? optional($assignment->enrolment->program)->name }} · {{ str_replace('_',' ', $assignment->status) }}</p>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @include('internship.student.partials.grade-status', ['gradeSummary' => $gradeSummary ?? []])
        @include('internship.student.partials.supervisors', ['supervisors' => $supervisors ?? []])

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

            <h5 style="font-weight:700;color:#0b3f90;">How your supervisor marks this task</h5>
            <p class="ip-meta mb-2">You need {{ $task->pass_mark }}/100 to pass. Cover all five points below in your report and evidence.</p>
            <table class="table ip-table mb-0">
                <thead><tr><th style="width:28%;">Criterion</th><th>What earns the marks</th><th class="text-right" style="width:80px;">Marks</th></tr></thead>
                <tbody>
                    @foreach(($criteria ?? []) as $c)
                        <tr>
                            <td><strong>{{ $c['label'] }}</strong></td>
                            <td class="ip-meta">{{ $c['guide'] }}</td>
                            <td class="text-right">{{ $c['max'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(in_array($assignment->status, ['available', 'revision_required'], true))
            <form method="POST" action="{{ route('internship.student.start', $assignment->id) }}" class="mb-3">
                @csrf
                <button class="ip-btn" type="submit"><i class="dripicons-media-play"></i> Start / Continue task</button>
            </form>
        @endif

        @if(in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true))
            <div class="ip-card ip-pending" id="ip-upload">
                <h5 style="font-weight:700;color:#0b3f90;">How to upload this task</h5>
                <ol class="ip-ol">
                    <li>Finish the work using the handbook and the checklist above. Tick each step as you go.</li>
                    <li>Write a short report in the first box (at least 20 characters): what you did, how you checked it, and any problem you solved.</li>
                    <li>Upload <strong>every screenshot this task asks for</strong>. Each screenshot has its own short-note box so your supervisor knows what they are looking at.</li>
                    <li>Need more than the slots shown? Click <strong>Add another screenshot</strong>. Allowed types: <strong>JPG, PNG, GIF, WEBP, or PDF</strong>, under <strong>10&nbsp;MB</strong> each, up to 15 files.</li>
                    <li>Click <strong>Submit for grading</strong>. Your supervisor is notified on WhatsApp.</li>
                    <li>Then fill your timesheet for this day. The next task is released on your next working day after they accept this one.</li>
                </ol>
                @if($assignment->status === 'revision_required')
                    <div class="alert alert-warning">Your supervisor asked for a revision. Upload a <strong>new</strong> set of files that addresses the feedback above. The previous attempt stays in your history.</div>
                @endif
                @if($task->submission_requirements)
                    <p class="mb-2"><strong>This day’s required evidence:</strong> {{ $task->submission_requirements }}</p>
                @endif
                <form method="POST" action="{{ route('internship.student.submit', $assignment->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>1. What you did (required)</label>
                        <textarea name="description" id="ip-desc" class="form-control" rows="6" required minlength="20" placeholder="Example: I completed the lab steps, captured the output screenshot, and verified the result against the handbook.">{{ old('description') }}</textarea>
                        <small class="ip-meta"><span id="ip-desc-count">0</span> / 20 characters minimum</small>
                    </div>
                    <div class="form-group mb-2">
                        <label>2. Screenshots and short notes (at least one required)</label>
                        <p class="ip-meta mb-2">One file per box. Add a short note so your supervisor can tell the shots apart. You can add more boxes if this task needs extra evidence.</p>
                        <div id="ip-evidence-list">
                            @foreach(($evidenceSlots ?? [['label' => 'Screenshot 1 — finished work', 'required' => true]]) as $i => $slot)
                                <div class="ip-evidence-row" data-index="{{ $i }}">
                                    <h6>{{ $slot['label'] }}@if(!empty($slot['required'])) <span class="ip-badge warn">Required</span>@endif</h6>
                                    <input type="file"
                                           name="evidence[{{ $i }}][file]"
                                           class="form-control ip-evidence-file"
                                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                                           @if(!empty($slot['required'])) required @endif>
                                    <input type="text"
                                           name="evidence[{{ $i }}][caption]"
                                           class="form-control mt-2"
                                           maxlength="400"
                                           value="{{ old('evidence.'.$i.'.caption') }}"
                                           placeholder="Short note — what this screenshot shows">
                                    <div class="ip-evidence-preview"></div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="ip-btn ip-btn-outline ip-btn-sm" id="ip-add-evidence">
                            <i class="dripicons-plus"></i> Add another screenshot
                        </button>
                        <small class="ip-meta d-block mt-1">JPG, PNG, GIF, WEBP, PDF · max 15 files · 10 MB each</small>
                    </div>
                    <button class="ip-btn" type="submit"><i class="dripicons-cloud-upload"></i> 3. Submit for grading</button>
                </form>
            </div>
        @elseif($assignment->status === 'submitted')
            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Upload is locked</h5>
                <p class="mb-0">You already submitted this task. Wait for your supervisor to accept it or request a revision. You will see the grade here and on your dashboard.</p>
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
                                <div class="mb-1">
                                    <a href="{{ route('internship.student.file', $f->id) }}" target="_blank">{{ $f->original_name }}</a>
                                    @if($f->caption)<span class="ip-meta"> — {{ $f->caption }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                        @foreach($sub->grades as $g)
                            @php $breakdown = \App\Support\InternshipRubric::breakdown($g, $task); @endphp
                            <div class="mt-2 p-2" style="background:#f8fafc;border-radius:8px;">
                                <strong>Grade:</strong> {{ $g->score }}/100 —
                                {{ $sub->status === 'passed' ? 'accepted' : str_replace('_', ' ', $sub->status) }}
                                @if($g->auto_accepted)<span class="ip-badge warn">Auto-accepted</span>@endif
                                @if(!empty($breakdown['rows']))
                                    <ul class="ip-file-list">
                                        @foreach($breakdown['rows'] as $row)
                                            <li>{{ $row['label'] }}: {{ $row['score'] }}@if(!is_null($row['max']))/{{ $row['max'] }}@endif</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if($g->feedback)<div class="mt-1">{{ $g->feedback }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
<script>
(function () {
    var desc = document.getElementById('ip-desc');
    var count = document.getElementById('ip-desc-count');
    if (desc && count) {
        var tick = function () { count.textContent = (desc.value || '').length; };
        desc.addEventListener('input', tick);
        tick();
    }
    var list = document.getElementById('ip-evidence-list');
    var addBtn = document.getElementById('ip-add-evidence');
    function bindPreview(row) {
        var input = row.querySelector('.ip-evidence-file');
        var preview = row.querySelector('.ip-evidence-preview');
        if (!input || !preview) return;
        input.addEventListener('change', function () {
            preview.textContent = '';
            var f = input.files && input.files[0];
            if (!f) return;
            var mb = (f.size / 1048576).toFixed(2);
            preview.textContent = f.name + ' (' + mb + ' MB)';
            if (f.size > 10 * 1048576) {
                preview.style.color = '#b91c1c';
                preview.textContent += ' — too large (max 10 MB)';
            } else {
                preview.style.color = '#64748b';
            }
        });
    }
    if (list) {
        Array.prototype.forEach.call(list.querySelectorAll('.ip-evidence-row'), bindPreview);
    }
    if (list && addBtn) {
        addBtn.addEventListener('click', function () {
            var next = list.querySelectorAll('.ip-evidence-row').length;
            if (next >= 15) {
                addBtn.disabled = true;
                return;
            }
            var row = document.createElement('div');
            row.className = 'ip-evidence-row';
            row.setAttribute('data-index', next);
            row.innerHTML = '<h6>Extra screenshot ' + (next + 1) + '</h6>'
                + '<input type="file" name="evidence[' + next + '][file]" class="form-control ip-evidence-file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">'
                + '<input type="text" name="evidence[' + next + '][caption]" class="form-control mt-2" maxlength="400" placeholder="Short note — what this screenshot shows">'
                + '<div class="ip-evidence-preview"></div>';
            list.appendChild(row);
            bindPreview(row);
            if (next + 1 >= 15) addBtn.disabled = true;
        });
    }
})();
</script>
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

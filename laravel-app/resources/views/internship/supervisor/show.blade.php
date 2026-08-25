@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php
    $assignment = $submission->assignment;
    $enrolment = $assignment->enrolment;
    $passMark = (int) ($task->pass_mark ?: 60);
    $totalHours = $hours->sum('hours');
@endphp
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.supervisor.index') }}" class="ip-btn ip-btn-outline mb-3">&larr; Queue</a>
        <h1 class="ip-title">Review submission</h1>
        <p class="ip-meta mb-3">
            Mark the work against the rubric below. Accepting it releases the next task on the student’s next working day;
            requesting a revision keeps them on this task until they resubmit.
        </p>

        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="ip-card">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Student:</strong> {{ optional($submission->student)->name }}</p>
                    <p class="mb-1"><strong>Programme:</strong> {{ optional(optional($enrolment)->program)->displayName() ?? optional(optional($enrolment)->program)->name }}</p>
                    <p class="mb-1"><strong>Task #{{ $assignment->progression_day }}:</strong> {{ $task->title }}</p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Attempt:</strong> {{ $submission->attempt_no }}</p>
                    <p class="mb-1"><strong>Submitted:</strong> {{ optional($submission->submitted_at)->format('D d M Y H:i') ?: '—' }}</p>
                    <p class="mb-1"><strong>Hours logged on this task:</strong>
                        @if($totalHours > 0)
                            {{ rtrim(rtrim(number_format($totalHours, 2), '0'), '.') }} h over {{ $hours->count() }} {{ $hours->count() === 1 ? 'day' : 'days' }}
                        @else
                            <span class="ip-badge warn">No timesheet entry</span>
                        @endif
                    </p>
                </div>
            </div>
            @if($submission->status === 'submitted' && !empty($sla['deadline']))
                <p class="mb-0 mt-2">
                    <span class="ip-badge {{ $sla['overdue'] ? 'warn' : 'active' }}">
                        Review due {{ $sla['deadline']->format('D d M Y H:i') }}
                    </span>
                    <span class="ip-meta">
                        @if($sla['overdue'])
                            Past the {{ $slaDays }}-working-day window — this will be auto-accepted on the next run.
                        @else
                            Auto-accepted after {{ $slaDays }} working day{{ $slaDays == 1 ? '' : 's' }} without a decision.
                        @endif
                    </span>
                </p>
            @elseif($submission->status !== 'submitted')
                <div class="alert alert-info mb-0 mt-2">
                    This submission is already marked <strong>{{ str_replace('_', ' ', $submission->status) }}</strong>, so it can no longer be graded.
                </div>
            @endif
        </div>

        <div class="ip-card">
            <h5 style="color:#0b3f90;font-weight:700;">What the student submitted</h5>
            <div class="ip-study-note">{{ $submission->description ?: 'No description given.' }}</div>
            <p class="ip-meta mb-1">Files ({{ $submission->files->count() }})</p>
            @if($submission->files->isEmpty())
                <p class="mb-0"><span class="ip-badge warn">No files attached</span></p>
            @else
                <ul class="ip-file-list">
                    @foreach($submission->files as $f)
                        <li>
                            <a href="{{ route('internship.supervisor.file', $f->id) }}" target="_blank">{{ $f->original_name }}</a>
                            <span class="ip-meta">({{ $f->size ? number_format($f->size / 1048576, 2).' MB' : 'size unknown' }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <details class="ip-day mb-3">
            <summary><span class="ip-day-num">Brief</span> <span class="ip-day-title">What this task asked for</span></summary>
            <div class="ip-day-body">
                @if($task->objective)<p><strong>Objective:</strong> {{ $task->objective }}</p>@endif
                @php $steps = $task->instructions(); @endphp
                @if($steps)
                    <ol class="ip-ol">
                        @foreach($steps as $step)<li>{{ is_array($step) ? ($step['text'] ?? json_encode($step)) : $step }}</li>@endforeach
                    </ol>
                @endif
                @if($task->submission_requirements)
                    <p class="mb-0"><strong>Required evidence:</strong> {{ $task->submission_requirements }}</p>
                @endif
            </div>
        </details>

        @if($hours->isNotEmpty())
            <details class="ip-day mb-3">
                <summary><span class="ip-day-num">Hours</span> <span class="ip-day-title">Timesheet entries logged against this task</span></summary>
                <div class="ip-day-body">
                    <table class="table ip-table mb-0">
                        <thead><tr><th>Date</th><th>Hours</th><th>Activity</th><th>Notes</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($hours as $h)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($h->entry_date)->format('D d M Y') }}</td>
                                    <td>{{ rtrim(rtrim(number_format($h->hours, 2), '0'), '.') }}</td>
                                    <td>{{ $h->activity_name ?: '—' }}</td>
                                    <td>{{ $h->notes ?: '—' }}</td>
                                    <td><span class="ip-badge {{ $h->status === 'approved' ? 'active' : ($h->status === 'rejected' ? 'warn' : '') }}">{{ $h->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @if($history->isNotEmpty())
            <details class="ip-day mb-3">
                <summary><span class="ip-day-num">History</span> <span class="ip-day-title">Earlier attempts on this task ({{ $history->count() }})</span></summary>
                <div class="ip-day-body">
                    @foreach($history as $past)
                        @php $pastGrade = $past->grades->first(); @endphp
                        <div class="ip-grade-box mb-2">
                            <div class="ip-meta">Attempt {{ $past->attempt_no }} · submitted {{ optional($past->submitted_at)->format('d M Y H:i') }}</div>
                            @if($pastGrade)
                                <p class="mb-1">
                                    <strong>{{ $pastGrade->score }}/100</strong>
                                    <span class="ip-badge {{ $pastGrade->decision === 'pass' ? 'active' : 'warn' }}">{{ str_replace('_', ' ', $pastGrade->decision) }}</span>
                                    <span class="ip-meta">by {{ optional($pastGrade->grader)->name ?: 'system' }}</span>
                                </p>
                                @if($pastGrade->feedback)<div class="ip-study-note mb-0">{{ $pastGrade->feedback }}</div>@endif
                            @else
                                <p class="mb-0 ip-meta">Not graded.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <div class="ip-card ip-pending">
            <h5 style="color:#0b3f90;font-weight:700;">Marking rubric</h5>
            <p class="ip-meta">
                Give a mark for every criterion. The total is converted to a score out of 100 and compared against the
                pass mark of <strong>{{ $passMark }}/100</strong>. Use the quick buttons for a fast, consistent mark.
            </p>

            <form method="POST" action="{{ route('internship.supervisor.grade', $submission->id) }}" id="ip-grade-form">
                @csrf
                <table class="table ip-table" id="ip-rubric">
                    <thead>
                        <tr>
                            <th style="width:26%;">Criterion</th>
                            <th>What earns the marks</th>
                            <th style="width:120px;">Mark</th>
                            <th style="width:230px;">Quick mark</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($criteria as $c)
                            <tr>
                                <td>
                                    <strong>{{ $c['label'] }}</strong>
                                    <div class="ip-meta">out of {{ $c['max'] }}</div>
                                </td>
                                <td class="ip-meta">{{ $c['guide'] ?: 'Mark against the task brief and the evidence submitted.' }}</td>
                                <td>
                                    <input type="number" class="form-control ip-rubric-input"
                                           name="rubric_scores[{{ $c['key'] }}]"
                                           data-max="{{ $c['max'] }}"
                                           min="0" max="{{ $c['max'] }}" step="1"
                                           value="{{ old('rubric_scores.'.$c['key']) }}"
                                           placeholder="0–{{ $c['max'] }}" required>
                                </td>
                                <td>
                                    @foreach(\App\Support\InternshipRubric::bands($c['max']) as $band)
                                        <button type="button" class="ip-btn ip-btn-outline ip-btn-sm ip-band mb-1"
                                                data-value="{{ $band['value'] }}" title="{{ $band['label'] }}">
                                            {{ $band['label'] }} {{ $band['value'] }}
                                        </button>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-right">Total</th>
                            <th colspan="2">
                                <span id="ip-total">0</span> / {{ $rubricTotal }} points
                                &nbsp;·&nbsp; <strong id="ip-percent">0</strong>/100
                                <span class="ip-badge" id="ip-verdict">Not marked</span>
                            </th>
                        </tr>
                    </tfoot>
                </table>

                <div class="form-group">
                    <label>Feedback to the student</label>
                    <textarea name="feedback" class="form-control" rows="4"
                              placeholder="What was done well, and what must be fixed. Required when you request a revision.">{{ old('feedback') }}</textarea>
                </div>

                <div class="form-group">
                    <label>Decision</label>
                    <div class="ip-check-item {{ old('decision', 'pass') === 'pass' ? 'is-done' : '' }}">
                        <label>
                            <input type="radio" name="decision" value="pass" {{ old('decision', 'pass') === 'pass' ? 'checked' : '' }}>
                            <span class="ip-check-text">
                                <strong>Accept submission</strong><br>
                                <span class="ip-meta">Task is marked passed and the next task is scheduled for the student’s next working day.</span>
                            </span>
                        </label>
                    </div>
                    <div class="ip-check-item">
                        <label>
                            <input type="radio" name="decision" value="revision_required" {{ old('decision') === 'revision_required' ? 'checked' : '' }}>
                            <span class="ip-check-text">
                                <strong>Request revision</strong><br>
                                <span class="ip-meta">Student keeps this task, fixes the work and resubmits. No new task is released.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="alert alert-warning" id="ip-below-pass" style="display:none;">
                    The total is below the pass mark of {{ $passMark }}/100. Raise the marks, request a revision, or record a waiver:
                    <div class="ip-check-item mt-2">
                        <label class="mb-0">
                            <input type="checkbox" name="accept_below_pass" value="1" {{ old('accept_below_pass') ? 'checked' : '' }}>
                            <span class="ip-check-text">Accept below the pass mark (recorded in the student’s feedback)</span>
                        </label>
                    </div>
                </div>

                <button class="ip-btn" type="submit" {{ $submission->status === 'submitted' ? '' : 'disabled' }}>Save decision</button>
            </form>
        </div>
    </div>
</section>

<script>
(function () {
    var form = document.getElementById('ip-grade-form');
    if (!form) { return; }
    var inputs = [].slice.call(form.querySelectorAll('.ip-rubric-input'));
    var totalEl = document.getElementById('ip-total');
    var percentEl = document.getElementById('ip-percent');
    var verdictEl = document.getElementById('ip-verdict');
    var belowBox = document.getElementById('ip-below-pass');
    var possible = {{ max(1, (int) $rubricTotal) }};
    var passMark = {{ $passMark }};

    function recalc() {
        var earned = 0, marked = 0;
        inputs.forEach(function (input) {
            var max = parseInt(input.getAttribute('data-max'), 10) || 0;
            var value = input.value === '' ? null : parseInt(input.value, 10);
            if (value === null || isNaN(value)) { return; }
            if (value > max) { input.value = max; value = max; }
            if (value < 0) { input.value = 0; value = 0; }
            earned += value;
            marked++;
        });

        var percent = Math.round(earned / possible * 100);
        totalEl.textContent = earned;
        percentEl.textContent = percent;

        var complete = marked === inputs.length && inputs.length > 0;
        verdictEl.className = 'ip-badge' + (complete ? (percent >= passMark ? ' active' : ' warn') : '');
        verdictEl.textContent = !complete
            ? 'Mark all ' + inputs.length + ' criteria'
            : (percent >= passMark ? 'Pass' : 'Below pass mark');

        var wantsPass = form.querySelector('input[name="decision"][value="pass"]').checked;
        belowBox.style.display = (complete && percent < passMark && wantsPass) ? '' : 'none';
    }

    inputs.forEach(function (input) { input.addEventListener('input', recalc); });
    [].slice.call(form.querySelectorAll('input[name="decision"]')).forEach(function (radio) {
        radio.addEventListener('change', recalc);
    });
    [].slice.call(form.querySelectorAll('.ip-band')).forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.closest('tr').querySelector('.ip-rubric-input');
            input.value = btn.getAttribute('data-value');
            recalc();
        });
    });

    form.addEventListener('submit', function (e) {
        var wantsRevision = form.querySelector('input[name="decision"][value="revision_required"]').checked;
        var feedback = form.querySelector('textarea[name="feedback"]').value.trim();
        if (wantsRevision && feedback === '') {
            e.preventDefault();
            alert('Tell the student what to fix before they resubmit.');
        }
    });

    recalc();
})();
</script>
@endsection

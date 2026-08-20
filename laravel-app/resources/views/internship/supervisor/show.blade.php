@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php $task = $submission->assignment->task; @endphp
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.supervisor.index') }}" class="ip-btn ip-btn-outline mb-3">&larr; Queue</a>
        <h1 class="ip-title">Review submission</h1>
        <div class="ip-card">
            <p><strong>Student:</strong> {{ optional($submission->student)->name }}</p>
            <p><strong>Task #{{ $submission->assignment->progression_day }}:</strong> {{ $task->title }}</p>
            <p><strong>Attempt:</strong> {{ $submission->attempt_no }}</p>
            @if($submission->status === 'submitted' && !empty($sla['deadline']))
                <p class="mb-2">
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
            @endif
            <p>{{ $submission->description }}</p>
            <p>
                @foreach($submission->files as $f)
                    <a href="{{ route('internship.supervisor.file', $f->id) }}" target="_blank">{{ $f->original_name }}</a>@if(!$loop->last), @endif
                @endforeach
            </p>
        </div>
        <div class="ip-card">
            <h5 style="color:#0b3f90;font-weight:700;">Marking guide (100 pts)</h5>
            <ul>
                @forelse($rubric as $k => $pts)
                    <li><strong>{{ str_replace('_',' ', $k) }}:</strong> {{ $pts }}</li>
                @empty
                    <li>Technical correctness 40 · Evidence 20 · Troubleshooting 15 · Documentation 15 · Safety 10</li>
                @endforelse
            </ul>
            <p class="ip-meta">Pass mark: {{ $task->pass_mark }}/100</p>
            <form method="POST" action="{{ route('internship.supervisor.grade', $submission->id) }}">
                @csrf
                <div class="form-group">
                    <label>Score (0–100)</label>
                    <input type="number" name="score" class="form-control" min="0" max="100" value="{{ old('score', 60) }}" required>
                </div>
                <div class="form-group">
                    <label>Feedback</label>
                    <textarea name="feedback" class="form-control" rows="4">{{ old('feedback') }}</textarea>
                </div>
                <div class="form-group">
                    <label>Decision</label>
                    <select name="decision" class="form-control" required>
                        <option value="pass">Accept submission</option>
                        <option value="revision_required">Request revision</option>
                    </select>
                    <small class="form-text text-muted">
                        Accepting schedules the next task for the student’s next working day.
                        Requesting a revision reopens this task and releases nothing new.
                    </small>
                </div>
                <button class="ip-btn" type="submit">Save decision</button>
            </form>
        </div>
    </div>
</section>
@endsection

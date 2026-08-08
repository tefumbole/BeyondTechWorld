@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.enrolments') }}" class="ip-btn ip-btn-outline mb-3">&larr; Enrolments</a>
        <h1 class="ip-title">Edit placement</h1>
        <p class="ip-meta mb-3">
            {{ optional($enrolment->student)->name }} · {{ optional($enrolment->program)->displayName() }}
            · Curriculum days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }}
        </p>
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="ip-card">
            <form method="POST" action="{{ route('internship.enrol.update', $enrolment->id) }}">
                @csrf
                <div class="form-group">
                    <label>Calendar start date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="{{ old('start_date', optional($enrolment->start_date)->format('Y-m-d') ?: $enrolment->start_date) }}">
                    <small class="text-muted">Tasks only release on/after this date (and on working days).</small>
                </div>
                @if(!$hasAssignments)
                    <div class="form-group">
                        <label>Start from curriculum day *</label>
                        <input type="number" name="start_curriculum_day" class="form-control" min="1" max="180" required
                               value="{{ old('start_curriculum_day', $enrolment->startCurriculumDay()) }}">
                        <small class="text-muted">Not necessarily day 1 — e.g. start at day 45 of the program bank.</small>
                    </div>
                    <div class="form-group">
                        <label>Duration (days)</label>
                        <input type="number" name="planned_duration_days" class="form-control"
                               min="{{ \App\Application::internshipDurationMin() }}"
                               max="{{ \App\Application::internshipDurationMax() }}"
                               value="{{ old('planned_duration_days', $enrolment->planned_duration_days ?: 90) }}"
                               placeholder="e.g. 19, 21, 30…">
                    </div>
                @elseif(!$hasOpen)
                    <div class="form-group">
                        <label>Next curriculum day to release</label>
                        <input type="number" name="next_curriculum_day" class="form-control" min="{{ $enrolment->startCurriculumDay() }}" max="{{ $enrolment->endCurriculumDay() }}"
                               value="{{ old('next_curriculum_day', $enrolment->nextCurriculumDay() ?: $enrolment->startCurriculumDay()) }}">
                        <small class="text-muted">Skip ahead within this placement (days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }}).</small>
                    </div>
                @else
                    <div class="alert alert-warning">A task is currently open. Finish or clear it before changing the curriculum day.</div>
                @endif
                <div class="form-group">
                    <label>Supervisor</label>
                    <select name="supervisor_id" class="form-control">
                        <option value="">Select…</option>
                        @foreach($supervisors as $u)
                            <option value="{{ $u->id }}" @if((string)old('supervisor_id', $enrolment->supervisor_id) === (string)$u->id) selected @endif>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $enrolment->notes) }}</textarea>
                </div>
                <button class="ip-btn" type="submit">Save placement</button>
            </form>
        </div>
    </div>
</section>
@endsection

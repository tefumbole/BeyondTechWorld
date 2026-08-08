@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.supervisor.students') }}" class="ip-btn ip-btn-outline mb-3">&larr; My students</a>
        <h1 class="ip-title">Place student</h1>
        <p class="ip-meta mb-3">
            {{ optional($enrolment->student)->name }} · {{ optional($enrolment->program)->displayName() }}
        </p>
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="ip-card">
            <form method="POST" action="{{ route('internship.supervisor.place.update', $enrolment->id) }}">
                @csrf
                <div class="form-group">
                    <label>Calendar start date</label>
                    <input type="date" name="start_date" class="form-control"
                           value="{{ old('start_date', optional($enrolment->start_date)->format('Y-m-d') ?: $enrolment->start_date) }}">
                    <small class="text-muted">When the student should begin receiving tasks.</small>
                </div>
                @if(!$hasAssignments)
                    <div class="form-group">
                        <label>Start from curriculum day *</label>
                        <input type="number" name="start_curriculum_day" class="form-control" min="1" max="180" required
                               value="{{ old('start_curriculum_day', $enrolment->startCurriculumDay()) }}">
                        <small class="text-muted">Choose any day in the program bank (1–180). Example: start at day 60 for a mid-track placement.</small>
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
                        <input type="number" name="next_curriculum_day" class="form-control"
                               min="{{ $enrolment->startCurriculumDay() }}" max="{{ $enrolment->endCurriculumDay() }}"
                               value="{{ old('next_curriculum_day', $enrolment->nextCurriculumDay() ?: $enrolment->startCurriculumDay()) }}">
                        <small class="text-muted">Jump the student ahead within days {{ $enrolment->startCurriculumDay() }}–{{ $enrolment->endCurriculumDay() }}.</small>
                    </div>
                @else
                    <div class="alert alert-warning">A task is currently open for this student. Grade or resolve it before changing the curriculum day.</div>
                @endif
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

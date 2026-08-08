@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.enrolments') }}" class="ip-btn ip-btn-outline mb-3">&larr; Enrolments</a>
        <h1 class="ip-title">Enrol student</h1>
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="ip-card">
            <form method="POST" action="{{ route('internship.enrol.store') }}">
                @csrf
                <div class="form-group">
                    <label>Student (ERP user) *</label>
                    <select name="student_user_id" class="form-control" required>
                        <option value="">Select…</option>
                        @foreach($students as $u)
                            <option value="{{ $u->id }}" @if(old('student_user_id')==$u->id) selected @endif>{{ $u->name }} — {{ $u->email }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Create the user first with role <strong>Intern</strong>.</small>
                </div>
                <div class="form-group">
                    <label>Program *</label>
                    <select name="program_id" class="form-control" required>
                        <option value="">Select…</option>
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }} v{{ $p->version }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Supervisor</label>
                    <select name="supervisor_id" class="form-control">
                        <option value="">Select…</option>
                        @foreach($supervisors as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Linked application (optional)</label>
                    <select name="application_id" class="form-control">
                        <option value="">None</option>
                        @foreach($applications as $a)
                            <option value="{{ $a->id }}">{{ $a->full_name }} — {{ $a->reference_number }} ({{ $a->status }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Start from curriculum day *</label>
                    <input type="number" name="start_curriculum_day" class="form-control" min="1" max="180" required
                           value="{{ old('start_curriculum_day', 1) }}">
                    <small class="text-muted">Not necessarily day 1 — student begins at this day in the program bank (1–180).</small>
                </div>
                <div class="form-group">
                    <label>Duration (days) *</label>
                    <input type="number" name="planned_duration_days" class="form-control" required
                           min="{{ \App\Application::internshipDurationMin() }}"
                           max="{{ \App\Application::internshipDurationMax() }}"
                           value="{{ old('planned_duration_days', 90) }}"
                           placeholder="e.g. 19, 21, 30…">
                    <small class="text-muted">Any length from {{ \App\Application::internshipDurationMin() }} to {{ \App\Application::internshipDurationMax() }} days (from the curriculum start day).</small>
                </div>
                <div class="form-group">
                    <label>Calendar start date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date', date('Y-m-d')) }}">
                    <small class="text-muted">First date tasks may be released (working days only).</small>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                </div>
                <button class="ip-btn" type="submit">Enrol &amp; activate</button>
            </form>
        </div>
    </div>
</section>
@endsection

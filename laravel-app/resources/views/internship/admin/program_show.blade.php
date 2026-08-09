@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php
    $focusDay = (int) session('focus_day', 0);
@endphp
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.programs') }}" class="ip-btn ip-btn-outline mb-3">&larr; All programs</a>

        <h1 class="ip-title">{{ $program->displayName() }}</h1>
        <p class="ip-meta mb-3">{{ $program->code }} · v{{ $program->version }} · {{ $program->tasks->count() }} curriculum days</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($canEdit)
        <div class="ip-card">
            <h5 style="color:#0b3f90;font-weight:700;margin-top:0;">Program details</h5>
            <form method="POST" action="{{ route('internship.programs.update', $program->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Name *</label>
                        <input class="form-control" name="name" value="{{ old('name', $program->name) }}" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Code *</label>
                        <input class="form-control" name="code" value="{{ old('code', $program->code) }}" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Status *</label>
                        <select class="form-control" name="status" required>
                            @foreach(['draft','published','archived'] as $st)
                                <option value="{{ $st }}" @if(old('status', $program->status) === $st) selected @endif>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Discipline</label>
                        <input class="form-control" name="discipline" value="{{ old('discipline', $program->discipline) }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Prerequisites</label>
                        <input class="form-control" name="prerequisites" value="{{ old('prerequisites', $program->prerequisites) }}">
                    </div>
                    <div class="col-md-12 form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3">{{ old('description', $program->description) }}</textarea>
                    </div>
                    <div class="col-md-12 form-group mb-0">
                        <label class="d-flex align-items-center" style="gap:8px;">
                            <input type="checkbox" name="is_active" value="1" @if(old('is_active', $program->is_active)) checked @endif>
                            Active (available for enrolments when published)
                        </label>
                    </div>
                </div>
                <button type="submit" class="ip-btn mt-3">Save program</button>
            </form>
        </div>
        @endif

        <div class="ip-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:8px;">
                <h5 style="color:#0b3f90;font-weight:700;margin:0;">Day-by-day curriculum</h5>
                <span class="ip-meta">{{ $program->tasks->count() }} days · click a day to {{ $canEdit ? 'edit' : 'view' }}</span>
            </div>

            @forelse($program->tasks as $t)
                @php
                    $steps = $t->instructions();
                    $open = $focusDay === (int) $t->day_number;
                @endphp
                <details class="ip-day" id="day-{{ $t->day_number }}" @if($open) open @endif>
                    <summary>
                        <span class="ip-day-num">Day {{ $t->day_number }}</span>
                        <span class="ip-day-title">{{ $t->title }}</span>
                        <span class="ip-meta">{{ $t->estimated_hours }}h · pass {{ $t->pass_mark }}%</span>
                    </summary>
                    <div class="ip-day-body">
                        @if($canEdit)
                            <form method="POST" action="{{ route('internship.programs.tasks.update', [$program->id, $t->id]) }}">
                                @csrf
                                <div class="form-group">
                                    <label>Title *</label>
                                    <input class="form-control" name="title" value="{{ $t->title }}" required>
                                </div>
                                <div class="form-group">
                                    <label>Objective</label>
                                    <textarea class="form-control" name="objective" rows="2">{{ $t->objective }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Instructions (one step per line)</label>
                                    <textarea class="form-control" name="instructions_text" rows="6">{{ implode("\n", $steps) }}</textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Hours</label>
                                        <input class="form-control" type="number" step="0.5" min="0.5" max="24" name="estimated_hours" value="{{ $t->estimated_hours }}">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Pass mark %</label>
                                        <input class="form-control" type="number" min="0" max="100" name="pass_mark" value="{{ $t->pass_mark }}">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Difficulty</label>
                                        <input class="form-control" name="difficulty" value="{{ $t->difficulty }}">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Tools</label>
                                        <input class="form-control" name="tools" value="{{ $t->tools }}">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Submission requirements</label>
                                    <textarea class="form-control" name="submission_requirements" rows="2">{{ $t->submission_requirements }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label class="d-inline-flex align-items-center mr-4" style="gap:6px;">
                                        <input type="checkbox" name="requires_supervisor_approval" value="1" @if($t->requires_supervisor_approval) checked @endif>
                                        Requires supervisor approval
                                    </label>
                                    <label class="d-inline-flex align-items-center" style="gap:6px;">
                                        <input type="checkbox" name="is_active" value="1" @if($t->is_active) checked @endif>
                                        Active
                                    </label>
                                </div>
                                <button type="submit" class="ip-btn">Save day {{ $t->day_number }}</button>
                            </form>
                        @else
                            @if($t->objective)
                                <p><strong>Objective:</strong> {{ $t->objective }}</p>
                            @endif
                            @if(!empty($steps))
                                <p class="mb-1"><strong>Instructions</strong></p>
                                <ol class="ip-ol">
                                    @foreach($steps as $step)<li>{{ $step }}</li>@endforeach
                                </ol>
                            @endif
                            @if($t->submission_requirements)
                                <p class="mb-0"><strong>Submit:</strong> {{ $t->submission_requirements }}</p>
                            @endif
                        @endif
                    </div>
                </details>
            @empty
                <p class="text-muted mb-0">No curriculum days yet. Import the curriculum JSON.</p>
            @endforelse
        </div>
    </div>
</section>
@if($focusDay)
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('day-{{ $focusDay }}');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
</script>
@endif
@endsection

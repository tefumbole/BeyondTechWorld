@extends('layout.main')

@section('content')
@php
    $editing = (bool) $job;
    $postingType = old('posting_type', $postingType ?? optional($job)->posting_type ?: 'job');
    $isInternship = $postingType === 'internship';
@endphp
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="mb-4">
            <h1 class="jb-title">
                @if($editing)
                    Edit {{ $isInternship ? 'Internship' : 'Job' }}
                @else
                    Add {{ $isInternship ? 'Internship' : 'Job' }}
                @endif
            </h1>
            <p class="jb-subtitle">
                {{ $editing ? 'Update this posting.' : ($isInternship ? 'Create a public internship advert (no salary).' : 'Create a public job posting with salary.') }}
            </p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="jb-card">
            <form method="POST" action="{{ $editing ? route('jobs.update', $job->id) : route('jobs.store') }}" id="job-form">
                @csrf
                <input type="hidden" name="posting_type" value="{{ $postingType }}">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="jb-label">Title *</label>
                        <input type="text" name="title" class="jb-field" required value="{{ old('title', optional($job)->title) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Status *</label>
                        <select name="status" class="jb-field" required>
                            @foreach(['active','draft','closed','archived'] as $st)
                                <option value="{{ $st }}" @if(old('status', optional($job)->status ?: 'active')===$st) selected @endif>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Department</label>
                        <input type="text" name="department" class="jb-field" value="{{ old('department', optional($job)->department) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Location</label>
                        <input type="text" name="location" class="jb-field" value="{{ old('location', optional($job)->location) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Employment Type</label>
                        <input type="text" name="employment_type" class="jb-field"
                               placeholder="{{ $isInternship ? 'Internship' : 'Full-Time' }}"
                               value="{{ old('employment_type', optional($job)->employment_type ?: ($isInternship ? 'Internship' : 'Full-Time')) }}">
                    </div>
                    @unless($isInternship)
                        <div class="col-md-4 mb-3">
                            <label class="jb-label">Salary *</label>
                            <input type="text" name="salary" class="jb-field" placeholder="e.g. 600,000 RWF"
                                   value="{{ old('salary', optional($job)->salary) }}">
                        </div>
                    @else
                        <div class="col-md-4 mb-3">
                            <label class="jb-label">Compensation</label>
                            <input type="text" class="jb-field" value="Unpaid internship" disabled>
                            <small class="text-muted">Internships do not include a salary field.</small>
                        </div>
                    @endunless
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Deadline</label>
                        <input type="date" name="deadline" class="jb-field" value="{{ old('deadline', optional($job)->deadline ? \Carbon\Carbon::parse($job->deadline)->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="jb-label">Max positions</label>
                        <input type="number" name="max_positions" min="1" class="jb-field" value="{{ old('max_positions', optional($job)->max_positions ?: 1) }}">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="jb-label">Max applicants</label>
                        <input type="number" name="max_applicants" min="1" class="jb-field" value="{{ old('max_applicants', optional($job)->max_applicants) }}">
                    </div>
                    <div class="col-12 mb-3">
                        <div class="jb-card" style="padding:14px 16px;border:1px solid #d4af37;background:#fffbeb;">
                            <label class="jb-label mb-1" style="color:#003D82;">Countdown timer</label>
                            <p class="text-muted mb-2" style="font-size:13px;margin:0 0 10px;">
                                Show a live days/hours/minutes/seconds countdown on the public Apply Now page. Requires a deadline above.
                            </p>
                            <label class="mb-0 d-flex align-items-center" style="gap:10px;font-weight:600;">
                                <input type="checkbox" name="enable_countdown" value="1" style="width:18px;height:18px;"
                                       @if(old('enable_countdown', optional($job)->enable_countdown ?? true)) checked @endif>
                                Enable deadline countdown on public page
                            </label>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="jb-label">Description</label>
                        <textarea name="description" class="jb-field" rows="4">{{ old('description', optional($job)->description) }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="jb-label">Requirements</label>
                        <textarea name="requirements" class="jb-field" rows="4">{{ old('requirements', optional($job)->requirements) }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="jb-label">Responsibilities</label>
                        <textarea name="responsibilities" class="jb-field" rows="4">{{ old('responsibilities', optional($job)->responsibilities) }}</textarea>
                    </div>
                </div>
                <div class="d-flex" style="gap:10px;">
                    <button type="submit" class="jb-btn">{{ $editing ? 'Save Changes' : ('Create '.($isInternship ? 'Internship' : 'Job')) }}</button>
                    <a href="{{ route('jobs.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

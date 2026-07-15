@extends('layout.main')

@section('content')
@php $editing = (bool) $job; @endphp
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="mb-4">
            <h1 class="jb-title">{{ $editing ? 'Edit Job' : 'Add Job' }}</h1>
            <p class="jb-subtitle">{{ $editing ? 'Update this posting.' : 'Create a new public job posting.' }}</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="jb-card">
            <form method="POST" action="{{ $editing ? route('jobs.update', $job->id) : route('jobs.store') }}">
                @csrf
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
                        <input type="text" name="employment_type" class="jb-field" placeholder="Full-Time" value="{{ old('employment_type', optional($job)->employment_type ?: optional($job)->type) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="jb-label">Salary</label>
                        <input type="text" name="salary" class="jb-field" value="{{ old('salary', optional($job)->salary) }}">
                    </div>
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
                    <div class="col-12 mb-3">
                        <label class="mb-0 d-flex align-items-center" style="gap:8px;">
                            <input type="checkbox" name="enable_countdown" value="1" @if(old('enable_countdown', optional($job)->enable_countdown ?? true)) checked @endif>
                            Enable deadline countdown on public page
                        </label>
                    </div>
                </div>
                <div class="d-flex" style="gap:10px;">
                    <button type="submit" class="jb-btn">{{ $editing ? 'Save Changes' : 'Create Job' }}</button>
                    <a href="{{ route('jobs.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid jb-shell">
        @include('job_board.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="jb-title">Edit application</h1>
                <p class="jb-subtitle mb-0">{{ $app->full_name }} · {{ $app->reference_number }}</p>
            </div>
            <div class="d-flex" style="gap:8px;">
                <a href="{{ route('jobs.applications.show', $app->id) }}" class="jb-btn-secondary">Cancel</a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('jobs.applications.details', $app->id) }}" class="jb-card">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Full name *</label>
                    <input type="text" name="full_name" class="jb-field" required value="{{ old('full_name', $app->full_name) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Email *</label>
                    <input type="email" name="email" class="jb-field" required value="{{ old('email', $app->email) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Phone</label>
                    <input type="tel" name="phone" class="jb-field" value="{{ old('phone', $app->phone) }}" placeholder="+237 6 82 79 42 29">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">WhatsApp</label>
                    <input type="tel" name="whatsapp_number" class="jb-field" value="{{ old('whatsapp_number', $app->whatsapp_number) }}" placeholder="+237 6 82 79 42 29">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Country</label>
                    <input type="text" name="country" class="jb-field" value="{{ old('country', $app->country) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">School / organisation</label>
                    <input type="text" name="school" class="jb-field" value="{{ old('school', $app->school) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Level of study</label>
                    <input type="text" name="level_of_study" class="jb-field" value="{{ old('level_of_study', $app->level_of_study) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Expected salary</label>
                    <input type="text" name="expected_salary" class="jb-field" value="{{ old('expected_salary', $app->expected_salary) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="jb-label">Availability</label>
                    <input type="text" name="availability" class="jb-field" value="{{ old('availability', $app->availability) }}">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="jb-label">Cover letter / motivation</label>
                    <textarea name="cover_letter" class="jb-field" rows="6">{{ old('cover_letter', $app->cover_letter) }}</textarea>
                </div>
            </div>
            <div class="d-flex flex-wrap" style="gap:8px;">
                <button type="submit" class="jb-btn"><i class="dripicons-checkmark"></i> Save application</button>
                <a href="{{ route('jobs.applications.show', $app->id) }}" class="jb-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
@endsection

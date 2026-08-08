@extends('layout.main')
@section('content')
@include('internship.partials.styles')
@php $task = $assignment->task; @endphp
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.student.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Task #{{ $assignment->progression_day }} — {{ $task->title }}</h1>
        <p class="ip-meta">{{ optional($assignment->enrolment->program)->name }} · {{ str_replace('_',' ', $assignment->status) }}</p>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="ip-card">
            <h5 style="font-weight:700;color:#0b3f90;">Objective</h5>
            <p>{{ $task->objective }}</p>
            <p class="ip-meta">Tools: {{ $task->tools ?: '—' }} · Est. {{ $task->estimated_hours }}h · Pass mark {{ $task->pass_mark }}/100</p>
            <h5 style="font-weight:700;color:#0b3f90;">Instructions</h5>
            <ol class="ip-ol">
                @foreach($task->instructions() as $line)
                    <li>{{ is_string($line) ? $line : json_encode($line) }}</li>
                @endforeach
            </ol>
            @if($task->submission_requirements)
                <h5 style="font-weight:700;color:#0b3f90;">Submission requirements</h5>
                <p>{{ $task->submission_requirements }}</p>
            @endif
        </div>

        @if(in_array($assignment->status, ['available', 'revision_required'], true))
            <form method="POST" action="{{ route('internship.student.start', $assignment->id) }}" class="mb-3">
                @csrf
                <button class="ip-btn" type="submit"><i class="dripicons-media-play"></i> Start / Continue task</button>
            </form>
        @endif

        @if(in_array($assignment->status, ['available', 'in_progress', 'revision_required'], true))
            <div class="ip-card">
                <h5 style="font-weight:700;color:#0b3f90;">Submit evidence</h5>
                <p class="ip-meta">Upload screenshots and/or PDF (max 10 files, 10MB each). Your supervisor will be notified on WhatsApp.</p>
                <form method="POST" action="{{ route('internship.student.submit', $assignment->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-control" rows="6" required minlength="20" placeholder="Describe what you did, how you verified it, and any issues you solved.">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Screenshots / PDF *</label>
                        <input type="file" name="files[]" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" multiple required>
                    </div>
                    <button class="ip-btn" type="submit"><i class="dripicons-cloud-upload"></i> Submit for grading</button>
                </form>
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
                                <a href="{{ route('internship.student.file', $f->id) }}" target="_blank">{{ $f->original_name }}</a>@if(!$loop->last), @endif
                            @endforeach
                        </div>
                        @foreach($sub->grades as $g)
                            <div class="mt-2 p-2" style="background:#f8fafc;border-radius:8px;">
                                <strong>Grade:</strong> {{ $g->score }}/100 — {{ str_replace('_',' ', $g->decision) }}
                                @if($g->feedback)<div class="mt-1">{{ $g->feedback }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection

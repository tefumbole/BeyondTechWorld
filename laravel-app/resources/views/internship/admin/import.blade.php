@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Import curriculum</h1>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="ip-card">
            <p>Imports <code>database/data/internship/beyond_180_day_curriculum_seed.json</code> (11 programs × 180 tasks). Idempotent by program code + version + day number.</p>
            <form method="POST" action="{{ route('internship.import.run') }}" class="d-inline">
                @csrf
                <input type="hidden" name="dry_run" value="1">
                <button class="ip-btn ip-btn-outline" type="submit">Validate only</button>
            </form>
            <form method="POST" action="{{ route('internship.import.run') }}" class="d-inline" onsubmit="return confirm('Import/publish all 11 programs?');">
                @csrf
                <button class="ip-btn" type="submit">Import &amp; publish</button>
            </form>
        </div>
    </div>
</section>
@endsection

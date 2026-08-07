@extends('layout.main')

@section('content')
@php $anTab = 'announcements.settings'; @endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="an-title"><i class="dripicons-gear"></i> Announcement Settings</h1>
            <p class="an-subtitle">Serial numbers, default header, and timezone for bulk WhatsApp.</p>
        </div>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="an-page-card">
            <h5>Configuration</h5>
            <p class="text-muted small">Matches Alpha Bridge announcements module defaults.</p>
            <form method="POST" action="{{ route('announcements.settings.update') }}">
                @csrf
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="{{ $settings->company_name }}">
                </div>
                <div class="form-group">
                    <label>Default Message Header</label>
                    <input type="text" name="default_header" class="form-control" value="{{ $settings->default_header }}">
                </div>
                <div class="alert alert-info py-2">
                    Announcement references now use the same series as Letters
                    (<strong>({{ \App\Support\LetterReference::prefix() }}/{{ \App\Support\LetterReference::yearToken() }}/#######)</strong>,
                    configured under General Settings → Letter Serial No.
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Timezone Offset</label>
                        <input type="text" name="timezone_offset" class="form-control" value="{{ $settings->timezone_offset }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Timezone</label>
                        <input type="text" name="timezone" class="form-control" value="{{ $settings->timezone }}">
                    </div>
                </div>
                <button class="an-btn-primary"><i class="dripicons-checkmark"></i> Save Settings</button>
            </form>
        </div>
    </div>
</section>
@endsection

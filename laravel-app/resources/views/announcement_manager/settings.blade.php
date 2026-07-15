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
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Serial Prefix</label>
                        <input type="text" name="serial_prefix" class="form-control" value="{{ $settings->serial_prefix }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Next Serial Number</label>
                        <input type="number" name="next_serial" class="form-control" value="{{ $settings->next_serial }}" min="1">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Serial Padding (digits)</label>
                        <input type="number" name="serial_padding" class="form-control" value="{{ $settings->serial_padding }}" min="1" max="8">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Timezone Offset</label>
                        <input type="text" name="timezone_offset" class="form-control" value="{{ $settings->timezone_offset }}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="{{ $settings->timezone }}">
                </div>
                <button class="an-btn-primary"><i class="dripicons-checkmark"></i> Save Settings</button>
            </form>
        </div>
    </div>
</section>
@endsection

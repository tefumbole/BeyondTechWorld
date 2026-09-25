@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">Appointments</h1>
        <p class="wa-sub">Times come from the windows below. WhatsApp does not invent a slot. Google Calendar stays disconnected until its credentials are set, and a booking is still saved here.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="wa-card mb-3">
            <h2 class="h5">Open a weekly window</h2>
            <form method="post" action="{{ route('whatsapp.appointments.availability') }}" class="form-inline">
                @csrf
                <select name="weekday" class="form-control form-control-sm mr-1">
                    @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $name)
                        <option value="{{ $i }}">{{ $name }}</option>
                    @endforeach
                </select>
                <input type="time" name="starts_time" class="form-control form-control-sm mr-1" required>
                <input type="time" name="ends_time" class="form-control form-control-sm mr-1" required>
                <input type="number" name="slot_minutes" class="form-control form-control-sm mr-1" value="60" min="15" max="240">
                <input type="text" name="location" class="form-control form-control-sm mr-1" placeholder="Location">
                <button class="btn btn-sm btn-primary" type="submit">Add window</button>
            </form>
        </div>
        <div class="wa-card table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Reference</th><th>When</th><th>With</th><th>Status</th><th>Calendar</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->reference }}</td>
                        <td>{{ $row->starts_at->format('j M Y H:i') }}</td>
                        <td>{{ $row->staff_label }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->google_sync_status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No appointments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

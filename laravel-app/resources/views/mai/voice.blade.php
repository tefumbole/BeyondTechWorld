@extends('layout.main')
@section('content')
<section>
    <div class="container-fluid">
        <h4>MAI Voice</h4>
        <p>Inbound calls for BeyondTechWorld. Recording is off. The Twilio WhatsApp sender is not connected, and the realtime bridge is off.</p>
        <form method="post" action="{{ route('mai.voice.switch') }}">
            @csrf
            <input type="hidden" name="enabled" value="{{ $status['enabled'] ? '0' : '1' }}">
            <button type="submit" class="btn btn-primary">MAI Voice: {{ $status['enabled'] ? 'ON' : 'OFF' }}</button>
        </form>
        <table class="table table-bordered" style="margin-top:16px">
            <tbody>
                <tr><th>Voice status</th><td>{{ $status['enabled'] ? 'ON' : 'OFF' }}</td></tr>
                <tr><th>Provider</th><td>{{ $status['provider'] }}</td></tr>
                <tr><th>Twilio WhatsApp sender</th><td>{{ $status['whatsapp_sender'] ?: 'Not connected' }}</td></tr>
                <tr><th>Voice application</th><td>{{ $status['voice_application'] }}</td></tr>
                <tr><th>Realtime bridge</th><td>{{ $status['realtime_bridge'] ? 'On' : 'Off' }}</td></tr>
                <tr><th>Business number</th><td>{{ $status['business_number'] ?: 'Not connected' }}</td></tr>
                <tr><th>AI enabled on the connection</th><td>{{ $status['ai_enabled'] ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Human transfer</th><td>{{ $status['transfer_configured'] ? 'Configured' : 'Not configured' }}</td></tr>
                <tr><th>Business hours</th><td>{{ $status['business_hours'] ? 'Configured' : 'Always open until hours are set' }}</td></tr>
                <tr><th>Assistant</th><td>{{ $status['assistant_name'] }} ({{ $status['language'] }})</td></tr>
                <tr><th>Active calls</th><td>{{ $status['active_calls'] }}</td></tr>
                <tr><th>Failed calls</th><td>{{ $status['failed_calls'] }}</td></tr>
                <tr><th>Average duration (seconds)</th><td>{{ $status['average_duration_seconds'] }}</td></tr>
                <tr><th>Transfer requests</th><td>{{ $status['transfer_requests'] }}</td></tr>
                <tr><th>Recording</th><td>Disabled</td></tr>
                <tr><th>Daily alert</th><td>{{ $status['daily_alert'] ? 'Usage is over the configured alert' : 'Within the configured alert' }}</td></tr>
            </tbody>
        </table>
        <h5>Recent calls</h5>
        <table class="table table-bordered">
            <thead>
                <tr><th>Call</th><th>Caller</th><th>Status</th><th>Handling</th><th>Transfer</th><th>Duration</th></tr>
            </thead>
            <tbody>
                @forelse($status['recent'] as $row)
                    <tr>
                        <td><a href="{{ route('mai.voice.show', $row->id) }}">{{ $row->id }}</a></td>
                        <td>{{ $row->caller_phone }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->handling }}</td>
                        <td>{{ $row->transfer_status }}</td>
                        <td>{{ $row->duration_seconds }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No calls yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

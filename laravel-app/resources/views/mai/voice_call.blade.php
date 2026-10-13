@extends('layout.main')
@section('content')
<section>
    <div class="container-fluid">
        <h4>Call {{ $call->id }}</h4>
        <p><a href="{{ route('mai.voice') }}">Back to MAI Voice</a></p>
        <table class="table table-bordered">
            <tbody>
                <tr><th>Tenant</th><td>{{ $call->cloud_tenant_id }}</td></tr>
                <tr><th>Provider call</th><td>{{ $call->provider_call_id }}</td></tr>
                <tr><th>Caller</th><td>{{ $call->caller_phone }}</td></tr>
                <tr><th>Called</th><td>{{ $call->called_number }}</td></tr>
                <tr><th>Status</th><td>{{ $call->status }} ({{ $call->status_reason }})</td></tr>
                <tr><th>Handling</th><td>{{ $call->handling }}</td></tr>
                <tr><th>Transfer</th><td>{{ $call->transfer_status }}</td></tr>
                <tr><th>Conversation</th><td>{{ $call->conversation_id }}</td></tr>
                <tr><th>Recording</th><td>{{ $call->recording ? 'On' : 'Off' }}</td></tr>
                <tr><th>Identity verified</th><td>{{ $call->identity_verified ? 'Yes' : 'No' }}</td></tr>
                <tr><th>Started</th><td>{{ $call->started_at }}</td></tr>
                <tr><th>Answered</th><td>{{ $call->answered_at }}</td></tr>
                <tr><th>Ended</th><td>{{ $call->ended_at }}</td></tr>
                <tr><th>Duration</th><td>{{ $call->duration_seconds }}</td></tr>
                <tr><th>Last response latency</th><td>{{ $call->last_latency_ms }} ms</td></tr>
            </tbody>
        </table>
        <h5>Transcript</h5>
        <table class="table table-bordered">
            <thead><tr><th>Role</th><th>Text</th><th>Time</th></tr></thead>
            <tbody>
                @forelse($transcript as $line)
                    <tr>
                        <td>{{ isset($line['role']) ? $line['role'] : '' }}</td>
                        <td>{{ isset($line['text']) ? $line['text'] : '' }}</td>
                        <td>{{ isset($line['at']) ? $line['at'] : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">No transcript.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <h1 class="wa-title">Message Tracking</h1>
        @include('whatsapp_hub.partials.range')
        <form method="get" class="form-inline mb-3">
            <input type="hidden" name="range" value="{{ $range['preset'] }}">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control mr-2 mb-2" placeholder="Body or provider ID">
            <select name="direction" class="form-control mr-2 mb-2">
                <option value="">All directions</option>
                <option value="INCOMING" {{ request('direction') === 'INCOMING' ? 'selected' : '' }}>Incoming</option>
                <option value="OUTGOING" {{ request('direction') === 'OUTGOING' ? 'selected' : '' }}>Outgoing</option>
            </select>
            <select name="status" class="form-control mr-2 mb-2">
                <option value="">All statuses</option>
                @foreach(['QUEUED','SENT','DELIVERED','READ','PLAYED','FAILED'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary mb-2" type="submit">Filter</button>
        </form>
        <div class="wa-card table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Direction</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Delivered</th>
                        <th>Read</th>
                        <th>Failed</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($messages as $m)
                    <tr>
                        <td>
                            @if($m->conversation_id)
                                <a href="{{ route('whatsapp.conversation', $m->conversation_id) }}">{{ optional($m->contact)->displayName() }}</a>
                            @else
                                {{ optional($m->contact)->displayName() }}
                            @endif
                        </td>
                        <td>{{ $m->direction }}</td>
                        <td>{{ $m->type }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($m->body, 50) }}</td>
                        <td>{{ $m->status }}</td>
                        <td>{{ $m->sent_at ?: '—' }}</td>
                        <td>{{ $m->delivered_at ?: '—' }}</td>
                        <td>{{ $m->read_at ?: '—' }}</td>
                        <td>{{ $m->failed_at ?: '—' }}</td>
                        <td>{{ $m->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted">No Hub messages in this range.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $messages->links() }}
        </div>
    </div>
</section>
@endsection

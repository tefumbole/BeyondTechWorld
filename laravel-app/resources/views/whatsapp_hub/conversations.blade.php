@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">Conversations</h1>
        <form method="get" class="form-inline mb-3">
            <input type="text" name="q" value="{{ $q }}" class="form-control mr-2 mb-2" placeholder="Name or phone">
            <button class="btn btn-primary mb-2" type="submit">Search</button>
        </form>
        <div class="wa-card wa-list">
            <table class="table mb-0">
                <thead><tr><th>Contact</th><th>Phone</th><th>Last message</th><th>Activity</th><th>Unread</th><th>Mode</th></tr></thead>
                <tbody>
                @forelse($list as $c)
                    <tr class="{{ $c->unread_count ? 'unread' : '' }}">
                        <td><a href="{{ route('whatsapp.conversation', $c->id) }}">{{ optional($c->contact)->displayName() }}</a></td>
                        <td>{{ optional($c->contact)->display_phone }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($c->last_message, 60) }}</td>
                        <td>{{ $c->last_activity_at }}</td>
                        <td>{{ $c->unread_count }}</td>
                        <td>{{ $c->mode }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No conversations yet. Incoming WhatsApp messages will appear here.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $list->links() }}
        </div>
    </div>
</section>
@endsection

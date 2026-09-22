@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <p><a href="{{ route('whatsapp.conversations') }}">&larr; All conversations</a></p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="wa-inbox">
            <div class="wa-inbox-side wa-card">
                <div class="small text-muted">Conversation</div>
                <strong>{{ optional($conversation->contact)->displayName() }}</strong>
                <div>{{ optional($conversation->contact)->display_phone }}</div>
                <div class="small mt-2">Mode: {{ $conversation->mode }}</div>
            </div>

            <div>
                <div class="wa-thread">
                    @foreach($messages as $m)
                        <div class="wa-bubble {{ $m->direction === 'OUTGOING' ? 'wa-out' : 'wa-in' }}">
                            <div>{{ $m->body ?: '['.$m->type.']' }}</div>
                            <div class="wa-meta">
                                {{ $m->created_at }}
                                @if($m->direction === 'OUTGOING')
                                    @php $tick = $m->ticks(); @endphp
                                    <span class="wa-ticks-{{ $tick }}">
                                        @if($tick === 'failed') failed
                                        @elseif($tick === 'read' || $tick === 'played') ✓✓ Read
                                        @elseif($tick === 'delivered') ✓✓ Delivered
                                        @elseif($tick === 'sent') ✓ Sent
                                        @else queued
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($canReply)
                    <form method="post" action="{{ route('whatsapp.conversation.reply', $conversation->id) }}" class="mt-3">
                        @csrf
                        <textarea name="body" class="form-control mb-2" rows="3" required placeholder="Reply…"></textarea>
                        <button class="btn btn-primary" type="submit">Send</button>
                    </form>
                @endif
            </div>

            <div class="wa-inbox-contact wa-card">
                <h5>Contact</h5>
                <div><strong>{{ optional($conversation->contact)->wa_name ?: '—' }}</strong></div>
                <div>{{ optional($conversation->contact)->display_phone }}</div>
                <hr>
                <div class="small text-muted">Linked ERP identities</div>
                @forelse(optional($conversation->contact)->links ?? [] as $link)
                    <div class="mb-1"><span class="wa-badge wa-badge-info">{{ ucfirst($link->role) }}</span>
                        #{{ $link->linkable_id }}
                        @if($link->linkable)
                            {{ $link->linkable->name ?? $link->linkable->full_name ?? '' }}
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">Unknown number — no ERP identity yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection

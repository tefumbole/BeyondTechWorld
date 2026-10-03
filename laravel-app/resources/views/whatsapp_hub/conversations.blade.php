@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">Conversations</h1>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="mb-3">
            <a class="btn btn-sm {{ ($mode ?? '') === '' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', request()->except(['mode','page'])) }}">All</a>
            <a class="btn btn-sm {{ ($mode ?? '') === 'HUMAN' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except('page'), ['mode' => 'HUMAN'])) }}">Human</a>
            <a class="btn btn-sm {{ ($mode ?? '') === 'AI' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except('page'), ['mode' => 'AI'])) }}">AI</a>
            <span class="mx-2 text-muted">|</span>
            <a class="btn btn-sm {{ ($channel ?? 'all') === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except(['channel','page']), ['channel' => 'all'])) }}">All channels</a>
            <a class="btn btn-sm {{ ($channel ?? '') === 'whatsapp' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except('page'), ['channel' => 'whatsapp'])) }}">WhatsApp</a>
            <a class="btn btn-sm {{ ($channel ?? '') === 'website' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except('page'), ['channel' => 'website'])) }}">Website</a>
        </div>
        <form method="get" class="form-inline mb-3" id="wa-inbox-filters">
            @if(!empty($mode))<input type="hidden" name="mode" value="{{ $mode }}">@endif
            @if(!empty($channel) && $channel !== 'all')<input type="hidden" name="channel" value="{{ $channel }}">@endif
            <input type="text" name="q" value="{{ $q }}" class="form-control mr-2 mb-2" placeholder="Name, phone or message">
            <select name="filter" class="form-control mr-2 mb-2">
                @foreach(['all'=>'All','unread'=>'Unread','awaiting'=>'Awaiting Response','mine'=>'Assigned to Me','unassigned'=>'Unassigned','website'=>'Website','customers'=>'Customers','leads'=>'Leads','employees'=>'Employees','interns'=>'Interns','closed'=>'Closed'] as $k=>$label)
                    <option value="{{ $k }}" {{ ($filter ?? 'all') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="assigned_user_id" class="form-control mr-2 mb-2">
                <option value="">Any staff</option>
                @foreach($staff as $u)
                    <option value="{{ $u->id }}" {{ (string) request('assigned_user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') !== request('to') ? request('from') : '' }}" class="form-control mr-2 mb-2" autocomplete="off">
            <input type="date" name="to" value="{{ request('from') !== request('to') ? request('to') : '' }}" class="form-control mr-2 mb-2" autocomplete="off">
            <button class="btn btn-primary mb-2" type="submit">Search</button>
            <span class="ml-2 small text-muted">Unread {{ $counts['unread'] ?? 0 }} · Awaiting {{ $counts['awaiting'] ?? 0 }}</span>
        </form>
        <form method="post" action="{{ route('whatsapp.conversations.delete') }}" id="delete-conversations" onsubmit="return confirm('Delete the selected conversations? Their messages will be removed.');">
            @csrf
            <p class="mb-2"><button class="btn btn-sm btn-danger" type="submit">Delete selected</button></p>
        </form>
        <div class="wa-card wa-list">
            <table class="table mb-0" id="wa-inbox-table">
                <thead><tr><th style="width:36px"><input type="checkbox" id="select-conversations" aria-label="Select all"></th><th>Contact</th><th>Channel</th><th>Phone</th><th>Last message</th><th>Waiting</th><th>Unread</th><th>Assigned</th><th>Mode</th><th></th></tr></thead>
                <tbody>
                @forelse($list as $c)
                    @php $wait = $c->waitingMinutes(); @endphp
                    <tr class="{{ $c->unread_count ? 'unread' : '' }} {{ $c->isAwaitingStaff() ? 'table-warning' : '' }}">
                        <td><input type="checkbox" name="ids[]" value="{{ $c->id }}" class="conversation-check" form="delete-conversations" aria-label="Select conversation"></td>
                        <td><a href="{{ route('whatsapp.conversation', $c->id) }}">{{ optional($c->contact)->displayName() }}</a></td>
                        <td><span class="badge badge-{{ $c->isWebsite() ? 'info' : 'secondary' }}">{{ $c->channelLabel() }}</span></td>
                        <td>{{ optional($c->contact)->display_phone }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($c->last_message, 60) }}</td>
                        <td>@if($c->isAwaitingStaff()) Customer waiting — {{ $wait }} min @else — @endif</td>
                        <td>{{ $c->unread_count }}</td>
                        <td>{{ optional($c->assignee)->name ?: 'Unassigned' }}</td>
                        <td>{{ $c->mode }}</td>
                        <td style="white-space:nowrap">
                            @if($c->mode === 'AI')
                                <form method="post" action="{{ route('whatsapp.conversation.takeover', $c->id) }}" style="margin:0">
                                    @csrf
                                    <button class="btn btn-sm btn-primary" type="submit">Take over</button>
                                </form>
                            @elseif($c->mode !== 'CLOSED')
                                <form method="post" action="{{ route('whatsapp.conversation.enable_ai', $c->id) }}" style="margin:0">
                                    @csrf
                                    <button class="btn btn-sm btn-success" type="submit">Hand to AI</button>
                                </form>
                            @endif
                            <button class="btn btn-sm btn-outline-danger ml-1" type="submit" form="delete-one-{{ $c->id }}" onclick="return confirm('Delete this conversation? Its messages will be removed.');">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-muted">No conversations match.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $list->appends(request()->query())->links() }}
        </div>
        @foreach($list as $c)
            <form id="delete-one-{{ $c->id }}" method="post" action="{{ route('whatsapp.conversation.delete', $c->id) }}" style="display:none">@csrf</form>
        @endforeach
    </div>
</section>
<script>
document.getElementById('select-conversations') && document.getElementById('select-conversations').addEventListener('change', function () {
    var on = this.checked;
    document.querySelectorAll('.conversation-check').forEach(function (box) { box.checked = on; });
});
(function () {
    var last = '{{ (int) ($counts["unread"] ?? 0) }}-{{ (int) ($counts["awaiting"] ?? 0) }}';
    setInterval(function () {
        if (document.hidden || !window.jQuery) return;
        var url = window.location.pathname + window.location.search + (window.location.search ? '&' : '?') + 'poll=1';
        jQuery.getJSON(url, function (data) {
            var next = ((data.counts && data.counts.unread) || 0) + '-' + ((data.counts && data.counts.awaiting) || 0);
            if (next !== last) { window.location.reload(); }
        });
    }, 15000);
})();
</script>
@endsection

@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <h1 class="wa-title">Conversations</h1>
        <div class="mb-3">
            <a class="btn btn-sm {{ ($mode ?? '') === '' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', request()->except(['mode','page'])) }}">All</a>
            <a class="btn btn-sm {{ ($mode ?? '') === 'HUMAN' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except('page'), ['mode' => 'HUMAN'])) }}">Human</a>
            <a class="btn btn-sm {{ ($mode ?? '') === 'AI' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.conversations', array_merge(request()->except('page'), ['mode' => 'AI'])) }}">AI</a>
        </div>
        <form method="get" class="form-inline mb-3" id="wa-inbox-filters">
            @if(!empty($mode))<input type="hidden" name="mode" value="{{ $mode }}">@endif
            <input type="text" name="q" value="{{ $q }}" class="form-control mr-2 mb-2" placeholder="Name, phone or message">
            <select name="filter" class="form-control mr-2 mb-2">
                @foreach(['all'=>'All','unread'=>'Unread','awaiting'=>'Awaiting Response','mine'=>'Assigned to Me','unassigned'=>'Unassigned','customers'=>'Customers','leads'=>'Leads','employees'=>'Employees','interns'=>'Interns','closed'=>'Closed'] as $k=>$label)
                    <option value="{{ $k }}" {{ ($filter ?? 'all') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="assigned_user_id" class="form-control mr-2 mb-2">
                <option value="">Any staff</option>
                @foreach($staff as $u)
                    <option value="{{ $u->id }}" {{ (string) request('assigned_user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control mr-2 mb-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control mr-2 mb-2">
            <button class="btn btn-primary mb-2" type="submit">Search</button>
            <span class="ml-2 small text-muted">Unread {{ $counts['unread'] ?? 0 }} · Awaiting {{ $counts['awaiting'] ?? 0 }}</span>
        </form>
        <div class="wa-card wa-list">
            <table class="table mb-0" id="wa-inbox-table">
                <thead><tr><th>Contact</th><th>Phone</th><th>Last message</th><th>Waiting</th><th>Unread</th><th>Assigned</th><th>Mode</th></tr></thead>
                <tbody>
                @forelse($list as $c)
                    @php $wait = $c->waitingMinutes(); @endphp
                    <tr class="{{ $c->unread_count ? 'unread' : '' }} {{ $c->isAwaitingStaff() ? 'table-warning' : '' }}">
                        <td><a href="{{ route('whatsapp.conversation', $c->id) }}">{{ optional($c->contact)->displayName() }}</a></td>
                        <td>{{ optional($c->contact)->display_phone }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($c->last_message, 60) }}</td>
                        <td>@if($c->isAwaitingStaff()) Customer waiting — {{ $wait }} min @else — @endif</td>
                        <td>{{ $c->unread_count }}</td>
                        <td>{{ optional($c->assignee)->name ?: 'Unassigned' }}</td>
                        <td>{{ $c->mode }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No conversations match.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $list->appends(request()->query())->links() }}
        </div>
    </div>
</section>
<script>
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

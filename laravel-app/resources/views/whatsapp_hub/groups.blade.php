@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">WhatsApp Groups</h1>
        <p class="wa-sub">Group name and member count. Download contacts for one group at a time.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if(!empty($listError))<div class="alert alert-danger">{{ $listError }}</div>@endif
        <p id="group-progress" class="text-muted mb-2" style="display:none">Loading group names. The rest appear about once a minute.</p>
        <p class="mb-3">
            <input id="group-filter" type="search" class="form-control" style="max-width:420px" placeholder="Search a group, for example NBC Praise Team">
        </p>
        <div class="wa-card table-responsive">
            <table class="table" id="group-table">
                <thead>
                    <tr><th>Group</th><th>Members</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    <tr data-jid="{{ $group['jid'] }}" data-known="{{ !empty($group['known']) ? '1' : '0' }}">
                        <td class="group-name">{{ !empty($group['known']) ? $group['name'] : 'Loading name…' }}</td>
                        <td class="group-members">{{ $group['members'] === null ? '…' : number_format($group['members']) }}</td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="{{ route('whatsapp.groups.export', ['jid' => $group['jid']]) }}">Download contacts</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">No groups were returned for this WhatsApp account.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <script>
            (function () {
                var input = document.getElementById('group-filter');
                var table = document.getElementById('group-table');
                var progress = document.getElementById('group-progress');
                var lookupUrl = @json(route('whatsapp.groups.lookup'));

                function rows() {
                    return Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-jid]'));
                }

                function applyFilter() {
                    if (!input) return;
                    var q = input.value.toLowerCase().trim();
                    rows().forEach(function (row) {
                        var name = (row.querySelector('.group-name') ? row.querySelector('.group-name').textContent : '').toLowerCase();
                        row.style.display = !q || name.indexOf(q) !== -1 ? '' : 'none';
                    });
                }

                if (input) input.addEventListener('input', applyFilter);

                function pendingJids() {
                    return rows().filter(function (row) {
                        return row.getAttribute('data-known') !== '1';
                    }).map(function (row) {
                        return row.getAttribute('data-jid');
                    });
                }

                function paint(group) {
                    var row = table.querySelector('tr[data-jid="' + group.jid.replace(/"/g, '\\"') + '"]');
                    if (!row || !group.name) return;
                    row.setAttribute('data-known', '1');
                    row.querySelector('.group-name').textContent = group.name;
                    row.querySelector('.group-members').textContent = group.members === null || group.members === undefined
                        ? '…'
                        : Number(group.members).toLocaleString();
                    var body = row.parentNode;
                    var placed = false;
                    Array.prototype.slice.call(body.querySelectorAll('tr[data-known="1"]')).forEach(function (other) {
                        if (other === row || placed) return;
                        if (group.name.toLowerCase() < other.querySelector('.group-name').textContent.toLowerCase()) {
                            body.insertBefore(row, other);
                            placed = true;
                        }
                    });
                    applyFilter();
                }

                function tick() {
                    var jids = pendingJids();
                    if (!jids.length) {
                        if (progress) progress.style.display = 'none';
                        return;
                    }
                    if (progress) {
                        var done = rows().length - jids.length;
                        progress.style.display = '';
                        progress.textContent = 'Loading group names (' + done + ' of ' + rows().length + '). The rest appear about once a minute.';
                    }
                    var batch = jids.slice(0, 8);
                    fetch(lookupUrl + '?jids=' + encodeURIComponent(batch.join(',')), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    }).then(function (response) {
                        return response.json();
                    }).then(function (payload) {
                        var found = payload && payload.groups ? payload.groups : [];
                        found.forEach(paint);
                        var wait = payload && payload.retry_after ? payload.retry_after : 60;
                        if (pendingJids().length) setTimeout(tick, Math.max(5, wait) * 1000);
                        else if (progress) progress.style.display = 'none';
                    }).catch(function () {
                        if (pendingJids().length) setTimeout(tick, 60000);
                    });
                }

                if (pendingJids().length) tick();
            })();
        </script>
    </div>
</section>
@endsection

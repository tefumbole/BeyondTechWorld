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
                    <tr>
                        <td>{{ $group['name'] }}</td>
                        <td>{{ number_format($group['members']) }}</td>
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
                var rows = document.querySelectorAll('#group-table tbody tr');
                if (!input) return;
                input.addEventListener('input', function () {
                    var q = input.value.toLowerCase().trim();
                    rows.forEach(function (row) {
                        var name = (row.cells[0] ? row.cells[0].textContent : '').toLowerCase();
                        row.style.display = !q || name.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            })();
        </script>
    </div>
</section>
@endsection

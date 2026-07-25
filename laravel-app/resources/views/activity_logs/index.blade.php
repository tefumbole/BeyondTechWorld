@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;"><i class="dripicons-document"></i> Activity Logs</h3>
                <p class="text-muted mb-0">Page views, clicks, form actions, and login events across the system.</p>
            </div>
            <div class="d-flex" style="gap:8px;">
                <a href="{{ route('activity-logs.index', request()->query()) }}" class="btn btn-sm btn-outline-primary"><i class="dripicons-clockwise"></i> Refresh</a>
                <form method="POST" action="{{ route('activity-logs.destroy') }}" onsubmit="return confirm('Clear ALL activity logs permanently?');">
                    @csrf
                    <input type="hidden" name="all" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i> Clear All</button>
                </form>
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        <ul class="nav nav-pills mb-3 flex-wrap" style="gap:6px;">
            @foreach([
                'all' => 'All',
                'navigation' => 'Navigation',
                'actions' => 'Actions',
                'clicks' => 'Clicks',
                'auth' => 'Auth',
            ] as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ ($tab ?? 'all') === $key ? 'active' : '' }}"
                       href="{{ route('activity-logs.index', array_filter(['tab' => $key, 'q' => $q ?: null, 'action' => ($action && $action !== 'all') ? $action : null])) }}">
                        {{ $label }}
                        <span class="badge badge-light">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="GET" class="card mb-3">
            <div class="card-body py-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="row align-items-end">
                    <div class="col-md-5 form-group mb-md-0">
                        <label class="small font-weight-bold">Search</label>
                        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="User, summary, path, IP…">
                    </div>
                    <div class="col-md-3 form-group mb-md-0">
                        <label class="small font-weight-bold">Action</label>
                        <select name="action" class="form-control">
                            @foreach(['all'=>'All actions','view'=>'view','click'=>'click','create'=>'create','update'=>'update','delete'=>'delete','action'=>'action','login'=>'login','logout'=>'logout','failed_login'=>'failed_login'] as $val => $lab)
                                <option value="{{ $val }}" @if(($action ?: 'all') === $val) selected @endif>{{ $lab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group mb-md-0">
                        <button type="submit" class="btn btn-primary btn-block">Filter</button>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('activity-logs.destroy') }}" id="logs-bulk-form">
            @csrf
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                    <strong>{{ $items->total() }} log(s)</strong>
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete selected logs?');">
                        Delete selected
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="logs-check-all"></th>
                                <th>When</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Summary</th>
                                <th>Path</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $log)
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $log->id }}" class="log-check"></td>
                                    <td class="small text-nowrap">{{ $log->created_at ? $log->created_at->format('M j, Y H:i:s') : '—' }}</td>
                                    <td class="small">
                                        <strong>{{ $log->user_name ?: '—' }}</strong>
                                        @if($log->user_role)<br><span class="text-muted">{{ $log->user_role }}</span>@endif
                                    </td>
                                    <td><span class="badge {{ $log->actionBadgeClass() }}">{{ $log->action }}</span></td>
                                    <td class="small">
                                        {{ $log->summary ?: '—' }}
                                        @if($log->entity)
                                            <br><span class="text-muted">{{ $log->entity }}@if($log->entity_id) #{{ $log->entity_id }}@endif</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($log->method)<code>{{ $log->method }}</code> @endif
                                        <span class="text-muted" title="{{ $log->path }}">{{ \Illuminate\Support\Str::limit($log->path, 48) }}</span>
                                        @if($log->status_code)<br><span class="text-muted">HTTP {{ $log->status_code }}</span>@endif
                                    </td>
                                    <td class="small text-muted">{{ $log->ip_address ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No activity logged yet. Browse the system and refresh.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($items, 'links'))
                    <div class="card-body">{{ $items->links() }}</div>
                @endif
            </div>
        </form>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    $('#logs-check-all').on('change', function () {
        $('.log-check').prop('checked', this.checked);
    });
})();
</script>
@endsection

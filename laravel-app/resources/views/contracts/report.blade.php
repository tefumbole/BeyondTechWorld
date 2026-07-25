@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h1 class="ct-title">Contracts Report</h1>
                <p class="ct-subtitle">Filter and export contract records.</p>
            </div>
            <a href="{{ route('contracts.dashboard') }}" class="ct-btn-secondary">Back to dashboard</a>
        </div>

        <div class="ct-card mb-3">
            <form method="GET" action="{{ route('contracts.report') }}" class="row align-items-end">
                <div class="col-md-2 mb-2">
                    <label class="ct-label">Status</label>
                    <select name="status" class="ct-field">
                        <option value="">All</option>
                        @foreach(['draft','in_review','ready_to_send','awaiting_client_signature','awaiting_admin_signature','signed','expired','cancelled','superseded'] as $st)
                            <option value="{{ $st }}" @if(($filters['status'] ?? '') === $st) selected @endif>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="ct-label">Type</label>
                    <select name="type_id" class="ct-field">
                        <option value="">All</option>
                        @foreach($types as $t)
                            <option value="{{ $t->id }}" @if(($filters['type_id'] ?? '') == $t->id) selected @endif>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="ct-label">From</label>
                    <input type="date" name="from" class="ct-field" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="ct-label">To</label>
                    <input type="date" name="to" class="ct-field" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-md-3 mb-2 d-flex" style="gap:8px;">
                    <button type="submit" class="ct-btn">Filter</button>
                    <a class="ct-btn-secondary" href="{{ route('contracts.report', array_merge($filters ?? [], ['export' => 'csv'])) }}">Export CSV</a>
                </div>
            </form>
        </div>

        <div class="ct-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Number</th><th>Title</th><th>Type</th><th>Status</th>
                            <th>Party B</th><th>Value</th><th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts as $c)
                            <tr>
                                <td><a href="{{ route('contracts.show', $c->id) }}">{{ $c->number }}</a></td>
                                <td>{{ $c->title }}</td>
                                <td>{{ optional($c->type)->name }}</td>
                                <td><span class="ct-badge">{{ $c->statusLabel() }}</span></td>
                                <td>{{ optional($c->partyB)->snapshot()['name'] ?? '—' }}</td>
                                <td>{{ $c->value ? number_format((float)$c->value, 0).' '.($c->currency ?? '') : '—' }}</td>
                                <td>{{ $c->created_at ? $c->created_at->format('Y-m-d') : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No contracts match.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $contracts->appends(request()->query())->links() }}</div>
        </div>
    </div>
</section>
@endsection

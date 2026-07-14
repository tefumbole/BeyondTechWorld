@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h4 class="mb-0"><i class="dripicons-view-list"></i> All Events</h4>
            @if(in_array('events.create', $all_permission))
                <a href="{{ route('events.create') }}" class="btn btn-primary"><i class="dripicons-plus"></i> Create Event</a>
            @endif
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="form-inline flex-wrap">
                    <input type="text" name="q" class="form-control mr-2 mb-2" placeholder="Search name, ref, venue..." value="{{ request('q') }}">
                    <select name="status" class="form-control mr-2 mb-2">
                        <option value="">All statuses</option>
                        @foreach(\App\Event::STATUSES as $k => $label)
                            <option value="{{ $k }}" {{ request('status') == $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="type" class="form-control mr-2 mb-2">
                        <option value="">All types</option>
                        @foreach(\App\Event::TYPES as $k => $label)
                            <option value="{{ $k }}" {{ request('type') == $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary mb-2">Filter</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background:#0b3f90;color:#fff;">
                        <tr>
                            <th>Reference</th>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Client</th>
                            <th>Event date</th>
                            <th>Status</th>
                            <th>Public</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $ev)
                            <tr>
                                <td><code>{{ $ev->reference_no }}</code></td>
                                <td><strong>{{ $ev->name }}</strong><br><small class="text-muted">{{ $ev->venue }}</small></td>
                                <td>{{ \App\Event::TYPES[$ev->event_type] ?? $ev->event_type }}</td>
                                <td>{{ optional($ev->customer)->name ?? '—' }}</td>
                                <td>{{ $ev->event_start_at ? $ev->event_start_at->format('d M Y H:i') : '—' }}</td>
                                <td><span class="badge badge-secondary">{{ $ev->statusLabel() }}</span></td>
                                <td>{{ optional($ev->publication)->publication_status ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('events.show', $ev->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No events found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($events->hasPages())
                <div class="card-footer">{{ $events->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection

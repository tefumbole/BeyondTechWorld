@extends('layout.main')

@section('content')
@include('events.partials.modern')
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-page-head">
            <div>
                <div class="ev-kicker">Events</div>
                <h4>{{ $heading }}</h4>
            </div>
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
                    <input type="hidden" name="view" value="{{ $view }}">
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
                            <th>Staff</th>
                            <th>Labour</th>
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
                                <td>{{ $ev->staffNames() ?: '—' }}</td>
                                <td>{{ number_format($ev->labourTotal()) }} XAF</td>
                                <td>{{ $ev->event_start_at ? $ev->event_start_at->format('d M Y H:i') : '—' }}</td>
                                <td><span class="badge badge-secondary">{{ $ev->statusLabel() }}</span></td>
                                <td>{{ optional($ev->publication)->publication_status ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if(in_array('events.update', $all_permission))
                                        <a href="{{ route('events.edit', $ev->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                    @endif
                                    <a href="{{ route('events.show', $ev->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    @if(!empty($canDelete))
                                        <form method="POST" action="{{ route('events.destroy', $ev->id) }}" class="d-inline" onsubmit="return confirm('Delete this event?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm {{ $ev->event_end_at && $ev->event_end_at->lt(now()->startOfDay()) ? 'btn-danger' : 'btn-outline-danger' }}">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No events found.</td></tr>
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

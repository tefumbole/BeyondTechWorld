@extends('layout.main')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .events-page .stat-card {
        border-radius: 14px;
        border: 1px solid #e3e9f4;
        background: #fff;
        padding: 18px 20px;
        box-shadow: 0 8px 20px rgba(11, 63, 144, 0.06);
        height: 100%;
    }
    .events-page .stat-card .value {
        font-size: 28px;
        font-weight: 800;
        color: #0b3f90;
        line-height: 1.1;
    }
    .events-page .stat-card .label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin-top: 6px;
    }
    .events-page .panel-card {
        border-radius: 14px;
        border: 1px solid #e3e9f4;
        background: #fff;
        overflow: hidden;
    }
    .events-page .panel-card .panel-head {
        background: linear-gradient(135deg, #0b3f90, #1456b8);
        color: #fff;
        padding: 14px 18px;
        font-weight: 700;
    }
    .event-status-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 999px;
        text-transform: uppercase;
    }
    .event-status-draft { background: #e2e8f0; color: #475569; }
    .event-status-planning, .event-status-approved { background: #dbeafe; color: #1d4ed8; }
    .event-status-event_in_progress { background: #fef3c7; color: #b45309; }
    .event-status-completed { background: #d1fae5; color: #047857; }
    .event-status-cancelled { background: #fee2e2; color: #b91c1c; }
    .event-status-postponed { background: #ffedd5; color: #c2410c; }
</style>

<section class="forms events-page">
    <div class="container-fluid">
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h4 class="mb-1"><i class="dripicons-calendar"></i> Events Dashboard</h4>
                <p class="text-muted mb-0">Overview of operational events, publication and upcoming work.</p>
            </div>
            @if(in_array('events.create', $all_permission))
                <a href="{{ route('events.create') }}" class="btn btn-primary"><i class="dripicons-plus"></i> Create Event</a>
            @endif
        </div>

        <div class="row mb-4">
            @foreach([
                ['total_upcoming', 'Upcoming Events'],
                ['today', 'Happening Today'],
                ['in_progress', 'In Progress'],
                ['starting_soon', 'Starting in 7 Days'],
                ['completed', 'Completed'],
                ['cancelled', 'Cancelled'],
                ['postponed', 'Postponed'],
                ['published_public', 'Published Public'],
                ['draft_public', 'Draft Public'],
                ['draft', 'Draft Internal'],
            ] as [$key, $label])
                <div class="col-6 col-md-4 col-lg-3 mb-3">
                    <div class="stat-card">
                        <div class="value">{{ $stats[$key] ?? 0 }}</div>
                        <div class="label">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="panel-card">
                    <div class="panel-head">Upcoming Events &amp; Reminder Actions</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Setup</th>
                                    <th>Event Date</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($upcoming as $ev)
                                    <tr>
                                        <td>
                                            <strong>{{ $ev->name }}</strong><br>
                                            <small class="text-muted">{{ $ev->reference_no }}</small>
                                        </td>
                                        <td>{{ $ev->setup_start_at ? $ev->setup_start_at->format('d M Y H:i') : '—' }}</td>
                                        <td>{{ $ev->event_start_at ? $ev->event_start_at->format('d M Y H:i') : '—' }}</td>
                                        <td>
                                            <span class="event-status-badge event-status-{{ $ev->internal_status }}">{{ $ev->statusLabel() }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('events.show', $ev->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No upcoming events yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mb-4">
                <div class="panel-card">
                    <div class="panel-head">Recent Events</div>
                    <ul class="list-group list-group-flush">
                        @forelse($recentEvents as $ev)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('events.show', $ev->id) }}"><strong>{{ $ev->name }}</strong></a>
                                    <div class="small text-muted">{{ $ev->updated_at->diffForHumans() }}</div>
                                </div>
                                <span class="event-status-badge event-status-{{ $ev->internal_status }}">{{ $ev->statusLabel() }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">No events created yet.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <strong>Phase 1 complete.</strong> Workforce, contracts, timesheets, payments and reminders arrive in later phases.
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

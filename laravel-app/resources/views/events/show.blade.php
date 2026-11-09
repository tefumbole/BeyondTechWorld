@extends('layout.main')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<section class="forms">
    <div class="container-fluid">
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @foreach(($paymentSchedules ?? collect()) as $schedule)
            @if($schedule->status === 'pending_approval')
                <div class="alert alert-warning">
                    <strong>A payment is waiting for admin approval.</strong>
                    {{ $schedule->amount ? number_format($schedule->amount).' XAF' : 'Engineer totals' }}
                    at {{ $schedule->pay_at ? $schedule->pay_at->format('d M Y H:i') : 'the chosen time' }}.
                    @if(!empty($isEventAdmin))
                        <form method="POST" action="{{ route('events.auto-pay.approve', $event->id) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                            <button class="btn btn-primary btn-sm ml-2">Approve payment</button>
                        </form>
                        <form method="POST" action="{{ route('events.auto-pay.reject', $event->id) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                            <button class="btn btn-light btn-sm">Cancel</button>
                        </form>
                    @endif
                </div>
            @elseif(in_array($schedule->status, ['scheduled', 'paying'], true))
                <div class="alert alert-info">
                    {{ $schedule->amount ? number_format($schedule->amount).' XAF' : 'Automatic payment' }}
                    {{ $schedule->status === 'paying' ? 'is being sent' : 'is set for '.optional($schedule->pay_at)->format('d M Y H:i') }}.
                </div>
            @endif
        @endforeach

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div class="d-flex">
                        @if($event->flyerUrl())
                            <img src="{{ $event->flyerUrl() }}" alt="" class="mr-3" style="width:100px;height:100px;object-fit:cover;border-radius:12px;">
                        @endif
                        <div>
                            <h4 class="mb-1">{{ $event->name }}</h4>
                            <p class="text-muted mb-1"><code>{{ $event->reference_no }}</code> · {{ \App\Event::TYPES[$event->event_type] ?? $event->event_type }}</p>
                            <span class="badge badge-primary">{{ $event->statusLabel() }}</span>
                            @if($event->publication)
                                <span class="badge badge-info ml-1">Public: {{ $event->publication->publication_status }}</span>
                            @endif
                            @if($event->slug)
                                <a href="{{ url('/events/' . $event->slug) }}" target="_blank" class="badge badge-success ml-1">Live page ↗</a>
                            @endif
                        </div>
                    </div>
                    <div class="mt-2">
                        @if(in_array('events.update', $all_permission))
                            <a href="{{ route('events.edit', $event->id) }}" class="btn btn-primary">Edit Event</a>
                        @endif
                        <a href="{{ route('events.dashboard') }}" class="btn btn-light">Dashboard</a>
                        @if(in_array('events.delete', $all_permission) || in_array('events.update', $all_permission))
                            <form method="POST" action="{{ route('events.destroy', $event->id) }}" class="d-inline" onsubmit="return confirm('Delete this event?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'overview' ? 'active font-weight-bold' : '' }}" href="{{ route('events.show', ['id' => $event->id, 'tab' => 'overview']) }}">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'publication' ? 'active font-weight-bold' : '' }}" href="{{ route('events.show', ['id' => $event->id, 'tab' => 'publication']) }}">Public Website</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'workforce' ? 'active font-weight-bold' : '' }}" href="{{ route('events.show', ['id' => $event->id, 'tab' => 'workforce']) }}">Workforce</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'contracts' ? 'active font-weight-bold' : '' }}" href="{{ route('events.show', ['id' => $event->id, 'tab' => 'contracts']) }}">Contracts</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'reminders' ? 'active font-weight-bold' : '' }}" href="{{ route('events.show', ['id' => $event->id, 'tab' => 'reminders']) }}">Reminders</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'payments' ? 'active font-weight-bold' : '' }}" href="{{ route('events.show', ['id' => $event->id, 'tab' => 'payments']) }}">Payments</a>
            </li>
        </ul>

        @if($tab === 'publication')
            @include('events.partials.tab_publication')
        @elseif($tab === 'workforce')
            @include('events.partials.tab_workforce')
        @elseif($tab === 'budget')
            @include('events.partials.tab_budget')
        @elseif($tab === 'contracts')
            @include('events.partials.tab_contracts')
        @elseif($tab === 'reminders')
            @include('events.partials.tab_reminders')
        @elseif($tab === 'payments')
            @include('events.partials.tab_payments')
        @else
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header font-weight-bold">Event details</div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Client</dt>
                                <dd class="col-sm-8">{{ optional($event->customer)->name ?? '—' }}</dd>
                                <dt class="col-sm-4">Venue</dt>
                                <dd class="col-sm-8">{{ $event->venue }} @if($event->city)— {{ $event->city }}@endif</dd>
                                <dt class="col-sm-4">Description</dt>
                                <dd class="col-sm-8">{{ $event->internal_description ?: '—' }}</dd>
                                <dt class="col-sm-4">Notes</dt>
                                <dd class="col-sm-8">{{ $event->internal_notes ?: '—' }}</dd>
                                <dt class="col-sm-4">Setup dates</dt>
                                <dd class="col-sm-8">
                                    @forelse(($setupEntries ?? []) as $entry)
                                        <div><strong>{{ date('d M Y', strtotime($entry['date'])) }}</strong>@if($entry['note'] !== '') — {{ $entry['note'] }}@endif</div>
                                    @empty
                                        —
                                    @endforelse
                                </dd>
                                <dt class="col-sm-4">Unpacking dates</dt>
                                <dd class="col-sm-8">
                                    @forelse(($unpackEntries ?? []) as $entry)
                                        <div><strong>{{ date('d M Y', strtotime($entry['date'])) }}</strong>@if($entry['note'] !== '') — {{ $entry['note'] }}@endif</div>
                                    @empty
                                        —
                                    @endforelse
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header font-weight-bold">Schedule</div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                @foreach(['event_start_at' => 'Event start', 'event_end_at' => 'Event end', 'setup_start_at' => 'Setup start'] as $field => $label)
                                    <tr>
                                        <td class="pl-3">{{ $label }}</td>
                                        <td>{{ $event->$field ? $event->$field->format('d M Y H:i') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header font-weight-bold">Quick stats</div>
                        <div class="card-body">
                            <p class="mb-1">Workers assigned: <strong>{{ $event->assignments->count() }}</strong></p>
                            <p class="mb-1">Labour allocated: <strong>{{ number_format($event->labourTotal()) }} XAF</strong></p>
                            @if($event->staffNames())
                                <p class="mb-2"><strong>Staff:</strong> {{ $event->staffNames() }}</p>
                            @endif
                            <a href="{{ route('events.show', ['id' => $event->id, 'tab' => 'payments']) }}" class="btn btn-sm btn-outline-primary">Payments</a>
                            @if(!empty($isEventAdmin))
                                <div class="mt-3">
                                    <div class="font-weight-bold mb-2">Schedule payment</div>
                                    <p class="small text-muted">Pay part of an engineer's event total at a time you choose. You can add more than one.</p>
                                    <form method="POST" action="{{ route('events.schedule-pay', $event->id) }}">
                                        @csrf
                                        <div class="form-group">
                                            <select name="assignment_id" class="form-control form-control-sm" required>
                                                <option value="">Choose an engineer</option>
                                                @foreach($event->assignments as $assignment)
                                                    @if($assignment->acceptance_status === 'rejected')
                                                        @continue
                                                    @endif
                                                    @php
                                                        $left = isset($payRooms[$assignment->id]) ? $payRooms[$assignment->id] : (int) $assignment->expected_total;
                                                        $who = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'Engineer';
                                                    @endphp
                                                    <option value="{{ $assignment->id }}">{{ $who }} — {{ number_format($left) }} XAF left of {{ number_format($assignment->expected_total) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <input type="number" name="amount" min="100" class="form-control form-control-sm" placeholder="Amount in XAF" required>
                                        </div>
                                        <div class="form-group">
                                            <input type="datetime-local" name="pay_at" class="form-control form-control-sm" required>
                                        </div>
                                        <button class="btn btn-primary btn-sm">Schedule payment</button>
                                    </form>
                                </div>
                            @endif
                            @if($rentalWarning)<p class="text-warning small mb-0">{{ $rentalWarning }}</p>@endif
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header font-weight-bold">Status history</div>
                        <ul class="list-group list-group-flush">
                            @foreach($event->statusHistories->take(5) as $h)
                                <li class="list-group-item small">
                                    <strong>{{ $h->new_status }}</strong>
                                    <div class="text-muted">{{ $h->changed_at->format('d M Y H:i') }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.querySelectorAll('.datetime-picker').forEach(function (el) {
    flatpickr(el, { enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true });
});
</script>
@endsection

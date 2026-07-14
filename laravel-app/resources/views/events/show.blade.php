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
                        <a href="{{ route('events.index') }}" class="btn btn-light">All Events</a>
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
                            <p class="mb-1">Labour allocated: <strong>{{ number_format($event->assignments->sum('expected_total')) }} XAF</strong></p>
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

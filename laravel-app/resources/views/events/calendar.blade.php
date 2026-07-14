@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <h4 class="mb-3"><i class="dripicons-calendar"></i> Event Calendar</h4>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead style="background:#0b3f90;color:#fff;">
                            <tr>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($events as $ev)
                                <tr>
                                    <td>{{ $ev->event_start_at->format('D, d M Y H:i') }}</td>
                                    <td>{{ $ev->name }}</td>
                                    <td>{{ $ev->venue }}</td>
                                    <td>{{ $ev->statusLabel() }}</td>
                                    <td><a href="{{ route('events.show', $ev->id) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No scheduled events.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0">Full interactive calendar view will be enhanced in a later phase.</p>
            </div>
        </div>
    </div>
</section>
@endsection

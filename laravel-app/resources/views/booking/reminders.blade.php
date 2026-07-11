@extends('layout.main')
@section('content')
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header mt-2">
                <h3 class="text-center">Booking Reminders</h3>
                <p class="text-center text-muted mb-0">Schedule WhatsApp reminders for bookings. Reminders are sent automatically at the chosen date and time.</p>
            </div>
            <div class="card-body">
                {!! Form::open(['route' => 'booking.reminders.store', 'method' => 'post']) !!}
                <div class="row">
                    <div class="col-md-4">
                        <label>Booking *</label>
                        <select name="booking_id" class="selectpicker form-control customer-type-search" data-live-search="true" required title="Select booking...">
                            @foreach($bookings as $booking)
                                <option value="{{ $booking->id }}" data-tokens="{{ $booking->reference_no }} {{ optional($booking->customer)->name }} {{ optional($booking->customer)->phone_number }}">
                                    {{ $booking->reference_no }} — {{ optional($booking->customer)->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Remind At *</label>
                        <input type="datetime-local" name="remind_at" class="form-control" required min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="col-md-4">
                        <label>Message (optional)</label>
                        <input type="text" name="message" class="form-control" maxlength="2000" placeholder="Custom reminder note for the client">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">Schedule</button>
                    </div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Booking Ref</th>
                        <th>Customer</th>
                        <th>Remind At</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Scheduled By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reminders as $reminder)
                        <tr>
                            <td>{{ optional($reminder->booking)->reference_no }}</td>
                            <td>{{ optional(optional($reminder->booking)->customer)->name }}</td>
                            <td>{{ $reminder->remind_at ? $reminder->remind_at->format('d M Y, H:i') : '' }}</td>
                            <td>{{ $reminder->message ?: '—' }}</td>
                            <td>
                                @if($reminder->sent_at)
                                    <span class="badge badge-success">Sent {{ $reminder->sent_at->format('d M Y, H:i') }}</span>
                                @else
                                    <span class="badge badge-warning">Pending</span>
                                @endif
                            </td>
                            <td>{{ optional($reminder->user)->name }}</td>
                            <td>
                                @if(!$reminder->sent_at)
                                    {!! Form::open(['route' => ['booking.reminders.destroy', $reminder->id], 'method' => 'DELETE', 'class' => 'd-inline']) !!}
                                        <button type="submit" class="btn btn-sm btn-link text-danger" onclick="return confirm('Cancel this reminder?')">Cancel</button>
                                    {!! Form::close() !!}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No reminders scheduled yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script type="text/javascript">
    $("ul#booking #booking-reminders-menu").addClass("active");
</script>
@endsection

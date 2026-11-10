@extends('layout.main')
@section('content')
@include('events.partials.modern')
@php
    $offerCounts = isset($offerCounts) ? $offerCounts : ['all' => 0, 'accepted' => 0, 'waiting' => 0, 'declined' => 0];
@endphp
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-page-head">
            <div>
                <div class="ev-kicker">Events</div>
                <h4>Payment requests</h4>
                <p>See who accepted the job, then approve the payments that are waiting.</p>
            </div>
        </div>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        @if((int) optional(auth()->user())->role_id === 1)
            <form method="POST" action="{{ route('events.payments.day') }}" class="card ev-pay-card mb-4">
                @csrf
                <div class="card-header">Pay what everyone is due</div>
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="col-md-4 form-group mb-md-0">
                            <label>Event</label>
                            <select name="event_id" class="form-control" required>
                                <option value="">Choose an event</option>
                                @foreach(($payEvents ?? []) as $payEvent)
                                    <option value="{{ $payEvent->id }}">{{ $payEvent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group mb-md-0">
                            <label>Working day</label>
                            <input type="date" name="work_date" id="pay-work-date" class="form-control">
                            <label class="d-block mt-2 mb-0"><input type="checkbox" name="all_days" value="1" id="pay-all-days"> All unpaid days</label>
                        </div>
                        <div class="col-md-3 form-group mb-md-0">
                            <label>Send a schedule on</label>
                            <input type="datetime-local" name="pay_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="do" value="schedule" class="btn btn-primary btn-block mb-2">Schedule</button>
                            <button type="submit" name="do" value="pay" class="btn btn-primary btn-block" style="background:#072f6b;" onclick="return confirm('Pay everyone who is due for the day you chose?');">Pay now</button>
                        </div>
                    </div>
                    <p class="small text-muted mb-0 mt-2">Choose one day, or all unpaid days. Schedule keeps the money for the time above. Pay sends it now.</p>
                </div>
            </form>
        @endif

        <div class="card ev-pay-card mb-4">
            <div class="card-header">Staff and labour on events</div>
            <nav class="ev-pay-tabs" aria-label="Offer status">
                <a class="{{ $offer === '' ? 'is-on' : '' }}" href="{{ route('events.payments.index') }}">Everyone <span>{{ $offerCounts['all'] }}</span></a>
                <a class="{{ $offer === 'accepted' ? 'is-on' : '' }}" href="{{ route('events.payments.index', ['offer' => 'accepted']) }}">Accepted <span>{{ $offerCounts['accepted'] }}</span></a>
                <a class="{{ $offer === 'waiting' ? 'is-on' : '' }}" href="{{ route('events.payments.index', ['offer' => 'waiting']) }}">Waiting <span>{{ $offerCounts['waiting'] }}</span></a>
                <a class="{{ $offer === 'declined' ? 'is-on' : '' }}" href="{{ route('events.payments.index', ['offer' => 'declined']) }}">Declined <span>{{ $offerCounts['declined'] }}</span></a>
            </nav>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Staff</th>
                            <th>Role</th>
                            <th>Offer</th>
                            <th>Labour</th>
                            <th>Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignments as $assignment)
                            @php
                                $acceptance = $assignment->acceptance_status ?: 'pending';
                                $payState = $assignment->payment_status ?: 'pending';
                            @endphp
                            <tr>
                                <td><a href="{{ route('events.show', ['id' => $assignment->event_id, 'tab' => 'payments']) }}">{{ optional($assignment->event)->name }}</a></td>
                                <td>{{ optional($assignment->workerProfile)->displayName() }}</td>
                                <td>{{ $assignment->assignment_role ?: '—' }}</td>
                                <td>
                                    @if($acceptance === 'accepted')
                                        <span class="ev-offer is-yes">Accepted</span>
                                        @if($assignment->accepted_at)
                                            <div class="small text-muted">{{ $assignment->accepted_at->format('d M Y, H:i') }}</div>
                                        @endif
                                    @elseif($acceptance === 'rejected')
                                        <span class="ev-offer is-no">Declined</span>
                                    @else
                                        <span class="ev-offer is-wait">Waiting</span>
                                    @endif
                                </td>
                                <td>{{ number_format($assignment->expected_total) }} XAF</td>
                                <td><span class="ev-pay-status is-{{ $payState }}">{{ $payState }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No engineers match this list.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card ev-pay-card">
            <div class="card-header">Recorded payments</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Event</th>
                            <th>Worker</th>
                            <th>Day's work</th>
                            <th>MoMo</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                            @php
                                $work = (string) $p->notes;
                                if (strpos($work, ' | ') !== false) {
                                    $bits = explode(' | ', $work, 2);
                                    $work = $bits[0].' — '.$bits[1];
                                }
                            @endphp
                            <tr>
                                <td><code>{{ $p->reference_no }}</code></td>
                                <td><a href="{{ route('events.show', ['id' => $p->event_id, 'tab' => 'payments']) }}">{{ $p->event->name }}</a></td>
                                <td>{{ $p->workerProfile->displayName() }}</td>
                                <td>{{ $work !== '' ? $work : '—' }}</td>
                                <td>{{ $p->mobile_money_number ?: '—' }}</td>
                                <td>{{ number_format($p->amount) }} XAF</td>
                                <td><span class="ev-pay-status is-{{ $p->status }}">{{ $p->statusLabel() }}</span></td>
                                <td>
                                    @if($p->status === 'pending' && in_array('event_payments.approve', $all_permission))
                                        <form method="POST" action="{{ route('events.payments.mark-paid', $p->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success">Approve</button>
                                        </form>
                                    @endif
                                    @if($p->receipt_path)
                                        <a href="{{ url($p->receipt_path) }}" target="_blank" class="btn btn-sm btn-light">Receipt</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No payment requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $payments->links() }}</div>
        </div>
    </div>
</section>
@endsection

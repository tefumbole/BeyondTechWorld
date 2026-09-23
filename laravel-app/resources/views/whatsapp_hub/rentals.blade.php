@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">Rental requests</h1>
        <p class="wa-sub">AI Generated quotations wait here until a manager approves and sends them. Editing stays in the existing quotation screen.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <div class="wa-card">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Guests</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->event_type ?: '—' }}</td>
                        <td>{{ $row->event_date ? $row->event_date->toDateString() : ($row->event_date_text ?: '—') }}</td>
                        <td>{{ $row->location ?: '—' }}</td>
                        <td>{{ $row->attendance ?: '—' }}</td>
                        <td>{{ $row->proposal_total !== null ? number_format($row->proposal_total, 0) : '—' }}</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            <a href="{{ route('whatsapp.conversation', $row->conversation_id) }}">Chat</a>
                            @if($row->quotation_id)
                                <a href="{{ route('quotations.edit', $row->quotation_id) }}">Edit quotation</a>
                                @if($row->status === 'AWAITING_STAFF_APPROVAL')
                                    <form method="post" action="{{ route('whatsapp.rentals.approve', $row->id) }}" style="display:inline">@csrf<button class="btn btn-sm btn-success" type="submit">Approve &amp; Send</button></form>
                                    <form method="post" action="{{ route('whatsapp.rentals.reject', $row->id) }}" style="display:inline">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">Reject</button></form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No rental requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

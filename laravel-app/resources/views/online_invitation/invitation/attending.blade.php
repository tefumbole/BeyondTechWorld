@extends('layout.main')
@section('content')
@php $oiTab = 'attending'; @endphp
@include('online_invitation.partials.tabs')
<section>
    <div class="container-fluid">
        <h3>Attending guests</h3>
        <p class="text-muted">Guests who accepted their invitation (RSVP).</p>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        <form method="GET" class="form-inline mb-3" style="gap:8px;">
            <select name="event_id" class="form-control">
                <option value="">All events</option>
                @foreach($events as $e)
                    <option value="{{ $e->id }}" {{ (int)$eventId === (int)$e->id ? 'selected' : '' }}>{{ $e->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="{{ route('online_invitation.invitations.index') }}">All invitations</a>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Guest</th>
                    <th>Phone</th>
                    <th>Event</th>
                    <th>Type</th>
                    <th>RSVP at</th>
                    <th>Admitted</th>
                </tr>
                </thead>
                <tbody>
                @forelse($data as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->recipient_name ?: optional($row->customer)->name ?: optional($row->user)->name }}</td>
                        <td>{{ $row->recipient_phone ?: optional($row->customer)->phone_number ?: optional($row->user)->phone }}</td>
                        <td>{{ optional($row->event)->name }}</td>
                        <td>{{ optional($row->category)->name }}</td>
                        <td>{{ optional($row->rsvp_at)->format('d M Y H:i') }}</td>
                        <td>{{ $row->used_at ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No accepted guests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $data->appends(request()->query())->links() }}
    </div>
</section>
<script>
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
</script>
@endsection

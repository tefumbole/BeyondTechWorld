@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h4 class="mb-0"><i class="dripicons-ticket"></i> Digital Invitations</h4>
            <div>
                @if(in_array('invitations.check_in', $all_permission) || in_array('invitations.edit', $all_permission))
                    <a href="{{ route('invitations.check_in') }}" class="btn btn-outline-primary"><i class="dripicons-checkmark"></i> Check-in</a>
                @endif
                @if(in_array('invitations.create', $all_permission))
                    <a href="{{ route('invitations.create') }}" class="btn btn-primary"><i class="dripicons-plus"></i> Create Invitation</a>
                @endif
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="form-inline flex-wrap">
                    <input type="text" name="q" class="form-control mr-2 mb-2" placeholder="Search guest, phone, code..." value="{{ request('q') }}">
                    <select name="status" class="form-control mr-2 mb-2">
                        <option value="">All statuses</option>
                        @foreach(['Pending','Sent','Failed','Checked In'] as $st)
                            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                    <select name="event_id" class="form-control mr-2 mb-2">
                        <option value="">All events</option>
                        @foreach($events as $ev)
                            <option value="{{ $ev->id }}" {{ request('event_id') == $ev->id ? 'selected' : '' }}>{{ $ev->title }}</option>
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
                            <th>Code</th>
                            <th>Guest</th>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Checked in</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invitations as $inv)
                            <tr>
                                <td><code>{{ $inv->qr_code }}</code></td>
                                <td>
                                    <strong>{{ $inv->displayName() }}</strong><br>
                                    <small class="text-muted">{{ $inv->displayPhone() }}</small>
                                </td>
                                <td>{{ optional($inv->event)->title ?: '—' }}</td>
                                <td>{{ $inv->invitation_type ?: 'Standard' }}</td>
                                <td><span class="badge badge-secondary">{{ $inv->status }}</span></td>
                                <td>{{ $inv->checked_in ? 'Yes' : 'No' }}</td>
                                <td>
                                    <a href="{{ route('invitations.show', $inv->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No invitations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($invitations->hasPages())
                <div class="card-footer">{{ $invitations->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection

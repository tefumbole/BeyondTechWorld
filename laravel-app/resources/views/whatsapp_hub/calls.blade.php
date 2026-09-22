@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <h1 class="wa-title">WhatsApp Calls</h1>
        <p class="wa-sub">Incoming call tracking only. The assistant does not answer voice calls.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="wa-card table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Caller</th>
                        <th>Contact</th>
                        <th>When</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($calls as $call)
                    <tr>
                        <td>{{ $call->caller_phone }}</td>
                        <td>{{ optional($call->contact)->displayName() ?: 'Unknown' }}</td>
                        <td>{{ $call->called_at }}</td>
                        <td>{{ $call->call_type }}</td>
                        <td colspan="4">
                            <form method="post" action="{{ route('whatsapp.calls.update', $call->id) }}" class="form-inline">
                                @csrf
                                <select name="status" class="form-control form-control-sm mr-1 mb-1">
                                    @foreach(['RECEIVED','MISSED','FOLLOW_UP_REQUIRED','CONTACTED','RESOLVED'] as $s)
                                        <option value="{{ $s }}" {{ $call->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <select name="assigned_user_id" class="form-control form-control-sm mr-1 mb-1">
                                    <option value="">Unassigned</option>
                                    @foreach($staff as $u)
                                        <option value="{{ $u->id }}" {{ (int) $call->assigned_user_id === (int) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="notes" value="{{ $call->notes }}" class="form-control form-control-sm mr-1 mb-1" placeholder="Notes">
                                <button class="btn btn-sm btn-primary mb-1" type="submit">Save</button>
                            </form>
                            <form method="post" action="{{ route('whatsapp.calls.followup', $call->id) }}" class="form-inline mt-1">
                                @csrf
                                <input type="hidden" name="me" value="1">
                                <input type="hidden" name="create_lead" value="1">
                                <button class="btn btn-sm btn-outline-warning mb-1" type="submit">Follow up + create lead</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No incoming calls recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $calls->links() }}
        </div>
    </div>
</section>
@endsection

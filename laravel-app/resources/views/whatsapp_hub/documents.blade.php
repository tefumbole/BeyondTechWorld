@extends('layout.main')
@section('content')
<section class="wa-hub">
    <div class="container-fluid">
        <h1 class="wa-title">Documents</h1>
        <div class="row">
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Requests today</div><p class="wa-stat">{{ $metrics['requests_today'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Awaiting code</div><p class="wa-stat">{{ $metrics['awaiting_otp'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Verified</div><p class="wa-stat">{{ $metrics['verified'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Sent</div><p class="wa-stat">{{ $metrics['sent'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Failed</div><p class="wa-stat">{{ $metrics['failed'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Denied</div><p class="wa-stat">{{ $metrics['denied'] }}</p></div></div>
            <div class="col-md-3"><div class="wa-card"><div class="wa-stat-label">Expired codes</div><p class="wa-stat">{{ $metrics['expired'] }}</p></div></div>
        </div>
        <div class="wa-card">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Requester</th>
                        <th>Identity</th>
                        <th>Document</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $row)
                    <tr>
                        <td>{{ $row->requested_at }}</td>
                        <td>{{ optional($row->contact)->displayName() }}</td>
                        <td>{{ $row->identity_type ?: '—' }}</td>
                        <td>{{ $row->document_type }}</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            @if($row->status === 'FAILED')
                                <form method="post" action="{{ route('whatsapp.documents.retry', $row->id) }}">
                                    {{ csrf_field() }}
                                    <button class="btn btn-sm btn-default" type="submit">Retry send</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No document requests.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

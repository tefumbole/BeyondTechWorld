@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>GBHS Kumbo</h3>
    @include('gbhs.admin.nav')
    <div class="row">
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Applications</div><strong>{{ $applications }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Waiting for review</div><strong>{{ $pending }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Members</div><strong>{{ $members }}</strong></div></div>
        <div class="col-md-3"><div class="card p-3"><div class="text-muted">Verified XAF</div><strong>{{ number_format($verified) }}</strong></div></div>
    </div>
    <div class="card mt-3 p-3">
        <h5>Messages</h5>
        @forelse($messages as $message)
            <div>{{ $message->created_at }} · {{ $message->name }} · {{ $message->body }}</div>
        @empty
            <p class="mb-0">No messages.</p>
        @endforelse
    </div>
</div></section>
@endsection

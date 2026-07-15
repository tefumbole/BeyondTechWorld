@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.pending'; @endphp
<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <h3 style="color:#0b3f90;">Pending Acceptances</h3>
        <p class="text-muted">Assignments waiting for assignees to accept. New tasks appear here as soon as they are created.</p>

        @forelse($assignments as $a)
            @php $u = $users->get($a->user_id); @endphp
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <span class="badge badge-warning">{{ optional($a->task)->priority }}</span>
                        <span class="badge badge-secondary">Pending</span>
                    </div>
                    <h5 class="mt-2 mb-1" style="color:#0b3f90;">{{ optional($a->task)->title }}</h5>
                    <p class="mb-1"><i class="dripicons-user"></i> Assignee: {{ optional($u)->name }} — {{ optional($u)->phone }}</p>
                    <p class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags(optional($a->task)->description), 160) }}</p>
                    <div class="small text-muted">
                        Due: {{ optional(optional($a->task)->deadline)->format('M d, Y') ?: '—' }}
                    </div>
                    <hr>
                    <div class="small text-muted">Waiting for assignee to accept via WhatsApp link</div>
                </div>
            </div>
        @empty
            <div class="alert alert-light border">No pending acceptances.</div>
        @endforelse
    </div>
</section>
@endsection

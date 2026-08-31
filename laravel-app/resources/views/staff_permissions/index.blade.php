@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid" style="max-width:1100px;margin:0 auto;">
        <div class="mb-3">
            <h3 style="color:#0b3f90;font-weight:800;">{{ $pageTitle }}</h3>
            <p class="text-muted mb-0">Staff permission requests from the public Permissions page.</p>
        </div>

        @include('staff_permissions.partials.tabs')

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        <form method="GET" class="card card-body mb-3">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label>Search</label>
                    <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Name, role, reference, reason…">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary btn-block">Filter</button>
                </div>
            </div>
        </form>

        <div class="card card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Role</th>
                            <th>Period</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->full_name }}</strong><br>
                                    <span class="text-muted small">{{ $item->email }} · {{ $item->phone }}</span>
                                    @if($item->subject)<br><span class="small font-semibold">{{ \Illuminate\Support\Str::limit($item->subject, 80) }}</span>@endif
                                    @if($item->reason)<br><span class="small text-muted">{{ \Illuminate\Support\Str::limit($item->reason, 80) }}</span>@endif
                                </td>
                                <td>{{ $item->company_role }}</td>
                                <td class="small">
                                    {{ $item->from_at ? $item->from_at->format('M j, Y H:i') : '—' }}<br>
                                    → {{ $item->to_at ? $item->to_at->format('M j, Y H:i') : '—' }}
                                </td>
                                <td><code>{{ $item->reference_number }}</code></td>
                                <td>
                                    @if($item->status === 'pending')
                                        <span class="badge badge-warning">Awaiting approval</span>
                                    @elseif($item->status === 'approved')
                                        <span class="badge badge-success">Approved</span>
                                    @else
                                        <span class="badge badge-danger">{{ $item->statusLabel() }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a class="btn btn-sm {{ $item->isPending() ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('permissions.show', $item->id) }}">
                                        {{ $item->isPending() ? 'Review' : 'View' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No permission records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($items, 'links'))
                <div class="mt-3">{{ $items->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection

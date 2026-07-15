@extends('layout.main')

@section('content')
@php $anTab = 'announcements.index'; @endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap" style="gap:12px;">
            <div>
                <h1 class="an-title"><i class="fa fa-bullhorn"></i> All Announcements</h1>
                <p class="an-subtitle">View and manage sent or draft announcements. Clone to resend with different recipients.</p>
            </div>
            <a href="{{ route('announcements.compose') }}" class="an-btn-primary"><i class="dripicons-plus"></i> Compose</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <form method="GET" class="form-inline mb-3">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control mr-2 mb-2" placeholder="Search subject or reference…">
            <select name="status" class="form-control mr-2 mb-2">
                <option value="all">All statuses</option>
                @foreach(['sent','scheduled','draft'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button class="an-btn-primary mb-2" type="submit">Filter</button>
        </form>

        <div class="an-page-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>WhatsApp</th>
                            <th>To / CC</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><code>{{ $item->reference ?: '—' }}</code></td>
                                <td><strong>{{ $item->subject }}</strong></td>
                                <td><span class="an-badge {{ $item->status }}">{{ $item->status }}</span></td>
                                <td><span class="an-badge {{ $item->whatsapp_status }}">{{ $item->whatsapp_status }}</span></td>
                                <td>{{ count($item->recipients()) }} / {{ count($item->ccRecipients()) }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('announcements.compose', ['clone' => $item->id]) }}" class="an-btn-outline" title="Clone & resend">Clone</a>
                                    <form method="POST" action="{{ route('announcements.destroy', $item->id) }}" class="d-inline" onsubmit="return confirm('Delete this announcement?');">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No announcements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->appends(request()->query())->links() }}</div>
        </div>
    </div>
</section>
@endsection

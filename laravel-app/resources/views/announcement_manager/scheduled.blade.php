@extends('layout.main')

@section('content')
@php $anTab = 'announcements.scheduled'; @endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="an-title"><i class="dripicons-clock"></i> Scheduled Announcements</h1>
            <p class="an-subtitle">Waiting for their send time (Africa/Kigali). Processed every minute.</p>
        </div>
        <div class="an-page-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Subject</th>
                            <th>Send at</th>
                            <th>Recipients</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><code>{{ $item->reference }}</code></td>
                                <td><strong>{{ $item->subject }}</strong></td>
                                <td>{{ optional($item->scheduled_for)->format('d M Y H:i') ?: '—' }}</td>
                                <td>{{ count($item->recipients()) }} (+{{ count($item->ccRecipients()) }} CC)</td>
                                <td class="text-right">
                                    <a href="{{ route('announcements.compose', ['clone' => $item->id]) }}" class="an-btn-outline">Clone</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No scheduled announcements.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
</section>
@endsection

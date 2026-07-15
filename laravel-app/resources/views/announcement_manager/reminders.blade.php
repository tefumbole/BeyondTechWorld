@extends('layout.main')

@section('content')
@php $anTab = 'announcements.reminders'; @endphp
<section class="forms">
    <div class="container-fluid an-shell">
        @include('announcement_manager.partials.tabs')
        <div class="mb-4">
            <h1 class="an-title"><i class="dripicons-clock"></i> Announcement Reminders</h1>
            <p class="an-subtitle">WhatsApp reminders before a scheduled announcement goes out.</p>
        </div>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        <div class="an-page-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Announcement</th>
                            <th>Reminder Time</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reminders as $reminder)
                            <tr>
                                <td>
                                    <strong>{{ optional($reminder->announcement)->subject ?: '—' }}</strong>
                                    <div class="small text-muted">{{ optional($reminder->announcement)->reference }}</div>
                                </td>
                                <td>{{ optional($reminder->reminder_time)->format('d M Y H:i') }}</td>
                                <td>
                                    @if($reminder->is_sent)
                                        <span class="an-badge sent">Sent</span>
                                    @else
                                        <span class="an-badge scheduled">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('announcements.reminders.delete', $reminder->id) }}" onsubmit="return confirm('Delete reminder?');">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No reminders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $reminders->links() }}</div>
        </div>
    </div>
</section>
@endsection

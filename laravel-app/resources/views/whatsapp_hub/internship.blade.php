@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">Internship operations</h1>
        <p class="wa-sub">WhatsApp intake only. Grading, progress and the next task stay in the internship module.</p>
        <div class="row">
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Submissions today</div><p class="wa-stat">{{ $metrics['submissions_today'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Awaiting supervisor review</div><p class="wa-stat">{{ $metrics['awaiting_review'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Corrections required</div><p class="wa-stat">{{ $metrics['corrections'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Failed media</div><p class="wa-stat">{{ $metrics['media_failures'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Unresolved task mapping</div><p class="wa-stat">{{ $metrics['unresolved'] }}</p></div></div>
            <div class="col-md-4"><div class="wa-card"><div class="wa-stat-label">Supervisor handovers</div><p class="wa-stat">{{ $metrics['handovers'] }}</p></div></div>
        </div>
        <div class="wa-card">
            <h5>Recent WhatsApp intakes</h5>
            <table class="table table-sm">
                <thead><tr><th>When</th><th>Intern</th><th>Assignment</th><th>Status</th><th>Submission</th></tr></thead>
                <tbody>
                @forelse($intakes as $row)
                    <tr>
                        <td>{{ $row->created_at }}</td>
                        <td>{{ $row->intern_user_id }}</td>
                        <td>{{ $row->assignment_id ?: '—' }}</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            @if($row->submission_id)
                                <a href="{{ route('internship.supervisor.show', $row->submission_id) }}">#{{ $row->submission_id }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No WhatsApp internship intakes yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="wa-card">
            <h5>Failed media imports</h5>
            @forelse($failures as $file)
                <div class="small mb-1">#{{ $file->id }} {{ $file->status }} — {{ $file->error }}</div>
            @empty
                <p class="text-muted mb-0">None.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection

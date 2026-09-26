@php
    $progress = $progress ?? [
        'planned' => 0,
        'completed' => 0,
        'remaining' => 0,
        'progress_percent' => 0,
        'overall_score' => null,
        'overall_percent' => null,
        'scored_count' => 0,
        'completed_tasks' => [],
        'current' => null,
    ];
    $current = $progress['current'] ?? ($assignment ?? null);
@endphp

@if ($current)
    <div class="ip-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px;">
            <div>
                <div class="ip-meta">Continue here</div>
                <strong style="font-size:1.1rem;color:#0b3f90;">
                    Task #{{ $current->progression_day }} — {{ optional($current->task)->title ?: 'Open task' }}
                </strong>
                <div class="ip-meta mt-1">Status: {{ str_replace('_', ' ', $current->status) }}</div>
            </div>
            <a class="ip-btn" href="{{ route('internship.student.task', $current->id) }}">
                <i class="dripicons-document-edit"></i>
                @if ($current->status === 'submitted') View submission
                @elseif ($current->status === 'revision_required') Fix and re-upload
                @else Open current task
                @endif
            </a>
        </div>
    </div>
@endif

<div class="ip-card">
    <h5 style="font-weight:700;color:#0b3f90;margin-bottom:.75rem;">Completed tasks &amp; remarks</h5>
    @if (empty($progress['completed_tasks']))
        <p class="text-muted mb-0">No accepted tasks yet. Complete and submit your current task to build this history.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="min-width:640px;">
                <thead>
                    <tr>
                        <th style="width:70px;">Task</th>
                        <th>Title</th>
                        <th style="width:90px;">Score</th>
                        <th>Supervisor remarks</th>
                        <th style="width:110px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($progress['completed_tasks'] as $row)
                        @php $item = $row['assignment']; @endphp
                        <tr>
                            <td><strong>#{{ $item->progression_day }}</strong></td>
                            <td>{{ optional($item->task)->title ?: 'Task' }}</td>
                            <td>
                                @if ($row['score'] !== null)
                                    <strong>{{ (int) $row['score'] }}%</strong>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if (! empty($row['feedback']))
                                    <div style="white-space:pre-wrap;">{{ $row['feedback'] }}</div>
                                    @if (! empty($row['grader']))
                                        <div class="ip-meta mt-1">— {{ $row['grader'] }}@if(!empty($row['auto_accepted'])) (auto-accepted)@endif</div>
                                    @endif
                                @elseif (! empty($row['auto_accepted']))
                                    <span class="text-muted">Accepted automatically — no written remarks.</span>
                                @else
                                    <span class="text-muted">No remarks recorded.</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('internship.student.task', $item->id) }}">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@php
    $progress = $progress ?? [
        'planned' => 0,
        'completed' => 0,
        'remaining' => 0,
        'progress_percent' => 0,
        'overall_score' => null,
        'scored_count' => 0,
        'current' => null,
    ];
    $current = $progress['current'] ?? ($assignment ?? null);
    $completedUrl = '#completed-tasks';
    $currentUrl = $current ? route('internship.student.task', $current->id) : null;
@endphp
<style>
    a.ip-stat-tile-link { display:block; text-decoration:none; color:inherit; }
    a.ip-stat-tile-link:hover .ip-stat-tile,
    a.ip-stat-tile-link:focus .ip-stat-tile { filter: brightness(1.05); }
</style>
<div class="row mb-3">
    <div class="col-md-3 col-6 mb-3">
        <a class="ip-stat-tile-link" href="{{ $completedUrl }}" title="Completed tasks">
            <div class="ip-stat-tile ip-stat-green">
                <div class="ip-meta">Completed tasks</div>
                <strong>{{ (int) $progress['completed'] }}</strong>
                <div class="ip-meta">of {{ (int) $progress['planned'] }} planned</div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="ip-stat-tile ip-stat-orange">
            <div class="ip-meta">Tasks remaining</div>
            <strong>{{ (int) $progress['remaining'] }}</strong>
            <div class="ip-meta">{{ number_format((float) $progress['progress_percent'], 1) }}% done</div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <a class="ip-stat-tile-link" href="{{ $completedUrl }}" title="Overall score">
            <div class="ip-stat-tile ip-stat-blue">
                <div class="ip-meta">Overall score</div>
                <strong>
                    @if ($progress['overall_score'] !== null)
                        {{ number_format((float) $progress['overall_score'], 1) }}%
                    @else
                        —
                    @endif
                </strong>
                <div class="ip-meta">
                    @if ((int) $progress['scored_count'] > 0)
                        Average of {{ (int) $progress['scored_count'] }} graded {{ (int) $progress['scored_count'] === 1 ? 'task' : 'tasks' }}
                    @else
                        No graded scores yet
                    @endif
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6 mb-3">
        @if ($currentUrl)
            <a class="ip-stat-tile-link" href="{{ $currentUrl }}" title="Current task">
                <div class="ip-stat-tile ip-stat-blue">
                    <div class="ip-meta">Current task</div>
                    <strong>#{{ $current->progression_day }}</strong>
                    <div class="ip-meta">{{ str_replace('_', ' ', $current->status) }}</div>
                </div>
            </a>
        @else
            <div class="ip-stat-tile ip-stat-blue">
                <div class="ip-meta">Current task</div>
                <strong>—</strong>
                <div class="ip-meta">None open</div>
            </div>
        @endif
    </div>
</div>

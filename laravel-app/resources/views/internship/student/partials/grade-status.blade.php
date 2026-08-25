@php
    $g = $gradeSummary ?? [];
    $status = $g['status'] ?? 'none';
    $badge = 'ip-badge';
    if ($status === 'passed') { $badge .= ' active'; }
    elseif (in_array($status, ['submitted', 'revision_required'], true)) { $badge .= ' warn'; }
    elseif (in_array($status, ['available', 'in_progress'], true)) { $badge .= ' blue'; }
@endphp
<div class="ip-grade-box ip-grade-{{ $status }}">
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
        <div>
            <div class="ip-meta">Grading status</div>
            <strong>{{ $g['label'] ?? '—' }}</strong>
            <span class="{{ $badge }}">{{ str_replace('_', ' ', $status) }}</span>
        </div>
        @if(!is_null($g['score'] ?? null))
            <div class="text-right">
                <div class="ip-meta">Score</div>
                <strong style="font-size:1.4rem;color:#0b3f90;">{{ $g['score'] }}/100</strong>
                @if(!empty($g['pass_mark']))
                    <div class="ip-meta">pass mark {{ $g['pass_mark'] }}</div>
                @endif
            </div>
        @endif
    </div>
    @php $rubric = $g['rubric'] ?? ['rows' => []]; @endphp
    @if(!empty($rubric['rows']))
        <h6 class="mt-3 mb-2" style="color:#0b3f90;font-weight:800;">Results breakdown</h6>
        <table class="table ip-table mt-0 mb-1">
            <thead><tr><th>Criterion</th><th class="text-right">Mark</th></tr></thead>
            <tbody>
                @foreach($rubric['rows'] as $row)
                    <tr>
                        <td>
                            {{ $row['label'] }}
                            @if(!empty($row['guide']))
                                <div class="ip-meta">{{ $row['guide'] }}</div>
                            @endif
                        </td>
                        <td class="text-right" style="white-space:nowrap;font-weight:700;">{{ $row['score'] }}@if(!is_null($row['max']))/{{ $row['max'] }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
            @if(!is_null($rubric['earned']) && !empty($rubric['possible']))
                <tfoot><tr><th>Total</th><th class="text-right">{{ $rubric['earned'] }}/{{ $rubric['possible'] }}@if(!is_null($rubric['percent'])) ({{ $rubric['percent'] }}%)@endif</th></tr></tfoot>
            @endif
        </table>
    @endif
    @if($status === 'submitted' && !empty($g['deadline']))
        <p class="ip-meta mb-0 mt-2">
            Supervisor review due by {{ $g['deadline']->format('D d M Y H:i') }}
            @if(!is_null($g['waiting_hours']))
                · waiting {{ $g['waiting_hours'] }} hour{{ (int) $g['waiting_hours'] === 1 ? '' : 's' }}
            @endif
        </p>
    @endif
    @if(!empty($g['grader']))
        <p class="ip-meta mb-0 mt-1">Reviewed by {{ $g['grader'] }}</p>
    @endif
    @if(!empty($g['feedback']))
        <div class="ip-study-note mt-2 mb-0">{{ $g['feedback'] }}</div>
    @endif
    @if($status === 'revision_required')
        <p class="mb-0 mt-2"><strong>What to do:</strong> Read the feedback, fix the work, then upload a new attempt below.</p>
    @endif
</div>

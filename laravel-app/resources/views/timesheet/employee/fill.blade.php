@extends('layout.main')

@section('content')
@php $tsTab = 'timesheet.fill'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.employee_tabs')

        <div class="mb-4">
            <h1 class="ts-title">Fill Time Sheet</h1>
            <p class="ts-subtitle">Log hours against activities you created. Categories are shared; activities are yours only.</p>
        </div>

        @if(!empty($weekScore))
            <div class="ts-week-bar {{ $weekScore['met'] ? 'is-met' : ($weekScore['overtime'] > 0 ? 'is-ot' : 'is-short') }}">
                <div>
                    <strong>This week</strong>
                    ({{ \Carbon\Carbon::parse($weekScore['week_start'])->format('D j M') }}
                    – {{ \Carbon\Carbon::parse($weekScore['week_end'])->format('D j M') }})
                </div>
                <div>
                    {{ number_format($weekScore['logged'], 2) }}h logged of {{ number_format($weekScore['expected'], 2) }}h expected
                    @if($weekScore['met'])
                        · Target met
                    @elseif($weekScore['overtime'] > 0)
                        · {{ number_format($weekScore['overtime'], 2) }}h overtime (supervisor approval needed)
                    @else
                        · {{ number_format($weekScore['remaining'], 2) }}h remaining this week
                    @endif
                </div>
            </div>
        @endif

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if(!empty($internPrompt))
            <div class="alert alert-warning">
                <strong>End-of-day timesheet.</strong>
                Interns must log hours for each working day. Select activity <em>Daily internship work</em> (or your own), enter hours, and save.
                @if(!empty($prefillDate))
                    Focus date: <strong>{{ $prefillDate }}</strong>.
                @endif
                @if(!empty($assignment))
                    <div class="mt-1">
                        Hours for: <strong>Day {{ $assignment->progression_day }}@if($assignment->task) — {{ $assignment->task->title }}@endif</strong>
                    </div>
                @endif
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="ts-card ts-card-accent">
                    <div class="d-flex align-items-center mb-1" style="gap:8px;">
                        <i class="dripicons-clock" style="color:#e8b923;"></i>
                        <h5 class="mb-0" style="color:#0b3f90;font-weight:700;">Log Time</h5>
                    </div>
                    <p class="text-muted small mb-3">Record hours for a specific activity.</p>
                    <form method="POST" action="{{ route('timesheet.entries.store') }}">
                        @csrf
                        @if(!empty($assignment))
                            <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                        @endif
                        <div class="mb-3">
                            <label class="ts-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" id="ts-date" class="ts-field" required value="{{ old('entry_date', $prefillDate ?? date('Y-m-d')) }}">
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Activity <span class="text-danger">*</span></label>
                            @if($activities->isEmpty())
                                <p class="small mb-2" style="color:#b45309;">
                                    You have no activities yet.
                                    <a href="{{ route('timesheet.activities') }}">Create one</a> — other people’s activities will not appear here.
                                </p>
                            @endif
                            <select name="activity_id" class="ts-field" required>
                                <option value="">{{ $activities->isEmpty() ? 'Create an activity first…' : 'Select your activity...' }}</option>
                                @foreach($activities as $act)
                                    @php
                                        $defaultIntern = !empty($internPrompt)
                                            && !old('activity_id')
                                            && stripos($act->name, 'internship') !== false;
                                    @endphp
                                    <option value="{{ $act->id }}" @if(old('activity_id')==$act->id || $defaultIntern) selected @endif>{{ $act->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Hours <span class="text-danger">*</span></label>
                            <input type="number" name="hours" id="ts-hours" class="ts-field" step="0.25" min="0.25" max="24" placeholder="e.g. 8.0" required value="{{ old('hours') }}">
                            <div id="ts-hours-hint" class="ts-hours-hint" role="status"></div>
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Notes</label>
                            <textarea name="notes" class="ts-field" rows="3" placeholder="Brief description of work done...">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="ts-btn"><i class="dripicons-document-edit"></i> Save Entry</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8 mb-4">
                <div class="ts-card">
                    <h5 class="mb-1" style="color:#0b3f90;font-weight:700;">Time Sheet History</h5>
                    <p class="text-muted small mb-3">Recent log entries (Sorted by date).</p>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Hours</th>
                                    <th>Notes</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($entries as $entry)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('M j, Y') }}</td>
                                        <td>{{ $entry->activity_name ?: '—' }}</td>
                                        <td>
                                            {{ number_format((float)$entry->hours, 2) }}
                                            @if(!empty($entry->requires_ot_approval) || $entry->status === 'overtime_pending')
                                                <div class="small" style="color:#b45309;font-weight:600;">OT pending</div>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ \Illuminate\Support\Str::limit($entry->notes, 40) ?: '—' }}</td>
                                        <td class="text-right text-nowrap">
                                            <button type="button" class="btn btn-link text-primary p-1" data-toggle="modal" data-target="#editEntry{{ $entry->id }}">
                                                <i class="dripicons-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('timesheet.entries.destroy', $entry->id) }}" class="d-inline" onsubmit="return confirm('Delete this entry?');">
                                                @csrf
                                                <button type="submit" class="btn btn-link text-danger p-1"><i class="dripicons-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No entries yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@foreach($entries as $entry)
<div class="modal fade" id="editEntry{{ $entry->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('timesheet.entries.update', $entry->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Edit Entry</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="ts-label">Date *</label>
                        <input type="date" name="entry_date" class="ts-field" value="{{ \Carbon\Carbon::parse($entry->entry_date)->format('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Activity *</label>
                        <select name="activity_id" class="ts-field" required>
                            @foreach($activities as $act)
                                <option value="{{ $act->id }}" @if($entry->activity_id==$act->id) selected @endif>{{ $act->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Hours *</label>
                        <input type="number" name="hours" class="ts-field" step="0.25" min="0.25" max="24" value="{{ $entry->hours }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="ts-label">Notes</label>
                        <textarea name="notes" class="ts-field" rows="3">{{ $entry->notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="ts-btn ts-btn-sm" style="width:auto;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<script>
(function () {
    var expectedByWeekday = @json($expectedByWeekday ?? []);
    var hoursByDate = @json($hoursByDate ?? []);
    var dateEl = document.getElementById('ts-date');
    var hoursEl = document.getElementById('ts-hours');
    var hintEl = document.getElementById('ts-hours-hint');
    if (!dateEl || !hoursEl || !hintEl) return;

    var dowKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    function weekdayKey(dateStr) {
        var d = new Date(dateStr + 'T12:00:00');
        if (isNaN(d.getTime())) return null;
        return dowKeys[d.getDay()];
    }
    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }
    function refresh() {
        var dateStr = dateEl.value;
        var day = weekdayKey(dateStr);
        var expected = day && expectedByWeekday[day] != null ? parseFloat(expectedByWeekday[day]) : 8;
        var logged = hoursByDate[dateStr] ? parseFloat(hoursByDate[dateStr]) : 0;
        var extra = parseFloat(hoursEl.value || '0') || 0;
        var projected = Math.round((logged + extra) * 100) / 100;
        var remaining = Math.round((expected - projected) * 100) / 100;
        var overtime = Math.round((projected - expected) * 100) / 100;
        hintEl.className = 'ts-hours-hint';
        if (!dateStr) {
            hintEl.textContent = '';
            return;
        }
        if (expected <= 0 && extra > 0) {
            hintEl.classList.add('is-ot');
            hintEl.textContent = 'This is not a scheduled working day. Hours will need supervisor overtime approval.';
            return;
        }
        if (extra <= 0) {
            if (logged <= 0) {
                hintEl.classList.add('is-remain');
                hintEl.textContent = fmt(expected) + 'h expected today. Enter hours to see what is still remaining.';
            } else if (remaining > 0.009) {
                hintEl.classList.add('is-remain');
                hintEl.textContent = fmt(remaining) + 'h still remaining to complete this working day (' + fmt(logged) + ' of ' + fmt(expected) + 'h logged).';
            } else if (overtime > 0.009) {
                hintEl.classList.add('is-ot');
                hintEl.textContent = fmt(overtime) + 'h overtime already logged. Supervisor will need to approve overtime.';
            } else {
                hintEl.classList.add('is-ok');
                hintEl.textContent = 'Working day complete (' + fmt(logged) + ' of ' + fmt(expected) + 'h).';
            }
            return;
        }
        if (remaining > 0.009) {
            hintEl.classList.add('is-remain');
            hintEl.textContent = fmt(remaining) + 'h still remaining to complete this working day (' + fmt(projected) + ' of ' + fmt(expected) + 'h).';
        } else if (overtime > 0.009) {
            hintEl.classList.add('is-ot');
            hintEl.textContent = fmt(overtime) + 'h overtime. Supervisor will need to approve overtime.';
        } else {
            hintEl.classList.add('is-ok');
            hintEl.textContent = 'Working day complete (' + fmt(projected) + ' of ' + fmt(expected) + 'h).';
        }
    }
    dateEl.addEventListener('change', refresh);
    hoursEl.addEventListener('input', refresh);
    refresh();
})();
</script>
@endsection

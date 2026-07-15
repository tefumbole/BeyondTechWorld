@extends('layout.main')

@section('content')
@php $tsTab = 'timesheet.fill'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.employee_tabs')

        <div class="mb-4">
            <h1 class="ts-title">Fill Time Sheet</h1>
            <p class="ts-subtitle">Log your daily work hours and activities.</p>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
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
                        <div class="mb-3">
                            <label class="ts-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="ts-field" required value="{{ old('entry_date', date('Y-m-d')) }}">
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Activity <span class="text-danger">*</span></label>
                            <select name="activity_id" class="ts-field" required>
                                <option value="">Select activity...</option>
                                @foreach($activities as $act)
                                    <option value="{{ $act->id }}" @if(old('activity_id')==$act->id) selected @endif>{{ $act->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="ts-label">Hours <span class="text-danger">*</span></label>
                            <input type="number" name="hours" class="ts-field" step="0.25" min="0.25" max="24" placeholder="e.g. 8.0" required value="{{ old('hours') }}">
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
                                        <td>{{ number_format((float)$entry->hours, 2) }}</td>
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
@endsection

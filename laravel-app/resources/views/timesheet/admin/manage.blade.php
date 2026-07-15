@extends('layout.main')

@section('content')
@php $tsaTab = 'timesheet.admin.manage'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.admin_tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="ts-title">Time Sheet Management</h1>
                <p class="ts-subtitle">Review and manage employee time logs.</p>
            </div>
            <a href="{{ route('timesheet.admin.report', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'generate' => 1, 'user_id' => $userId]) }}" class="ts-btn ts-btn-sm">
                <i class="dripicons-download"></i> Export
            </a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="ts-card mb-4">
            <form method="GET" action="{{ route('timesheet.admin.manage') }}" class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="ts-label">Filter by Employee</label>
                    <select name="user_id" class="ts-field" onchange="this.form.submit()">
                        <option value="all" @if(($userId ?? 'all')==='all') selected @endif>All Employees</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @if(($userId ?? '')==(string)$emp->id) selected @endif>{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="ts-label">Filter by Month</label>
                    <input type="month" name="month" class="ts-field" value="{{ $month }}" onchange="this.form.submit()">
                </div>
            </form>
        </div>

        <div class="ts-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
                <h5 class="mb-0" style="color:#0b3f90;font-weight:700;">Logged Entries</h5>
                <span class="ts-badge" style="background:#dbeafe;color:#1e40af;">Total Hours: {{ number_format($totalHours, 1) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Activity</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $entry)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('M j, Y') }}</td>
                                <td>{{ $entry->employee_name ?: '—' }}</td>
                                <td>{{ $entry->activity_name ?: '—' }}</td>
                                <td>{{ number_format((float)$entry->hours, 2) }}</td>
                                <td><span class="ts-badge">{{ ucfirst($entry->status) }}</span></td>
                                <td class="text-right text-nowrap">
                                    @if($entry->status !== 'approved')
                                        <form method="POST" action="{{ route('timesheet.admin.entries.status', $entry->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Approve">Approve</button>
                                        </form>
                                    @endif
                                    @if($entry->status !== 'rejected')
                                        <form method="POST" action="{{ route('timesheet.admin.entries.status', $entry->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reject">Reject</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('timesheet.admin.entries.destroy', $entry->id) }}" class="d-inline" onsubmit="return confirm('Delete this entry?');">
                                        @csrf
                                        <button type="submit" class="btn btn-link text-danger p-1" title="Delete"><i class="dripicons-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No entries found matching filters.</td>
                            </tr>
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

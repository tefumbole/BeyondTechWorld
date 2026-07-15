@extends('layout.main')

@section('content')
@php $tsaTab = 'timesheet.admin.report'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.admin_tabs')

        <div class="mb-4">
            <h1 class="ts-title">Time Sheet Reports</h1>
            <p class="ts-subtitle">Generate comprehensive time logs and variance reports.</p>
        </div>

        <div class="ts-card mb-4">
            <form method="GET" action="{{ route('timesheet.admin.report') }}" class="row align-items-end" style="gap:0;">
                <input type="hidden" name="generate" value="1">
                <div class="col-md-3 mb-2">
                    <label class="ts-label">From Date</label>
                    <input type="date" name="from" class="ts-field" value="{{ $from }}" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="ts-label">To Date</label>
                    <input type="date" name="to" class="ts-field" value="{{ $to }}" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="ts-label">Employee</label>
                    <select name="user_id" class="ts-field">
                        <option value="all" @if(($userId ?? 'all')==='all') selected @endif>All Employees</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" @if(($userId ?? '')==(string)$emp->id) selected @endif>{{ $emp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="submit" class="ts-btn"><i class="dripicons-experiment"></i> Generate Report</button>
                </div>
            </form>
        </div>

        @if($report)
            <div class="row mb-3">
                <div class="col-md-4 mb-2">
                    <div class="ts-card text-center py-3">
                        <div class="text-muted small">TOTAL HOURS</div>
                        <div style="font-size:1.75rem;font-weight:800;color:#0b3f90;">{{ number_format($report['total_hours'], 2) }}h</div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="ts-card text-center py-3">
                        <div class="text-muted small">ENTRIES</div>
                        <div style="font-size:1.75rem;font-weight:800;color:#0b3f90;">{{ $report['rows']->count() }}</div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="ts-card text-center py-3">
                        <div class="text-muted small">EMPLOYEES</div>
                        <div style="font-size:1.75rem;font-weight:800;color:#0b3f90;">{{ $report['by_employee']->count() }}</div>
                    </div>
                </div>
            </div>

            @if($report['by_employee']->count())
                <div class="ts-card mb-4">
                    <h5 style="color:#0b3f90;font-weight:700;">By Employee</h5>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Employee</th><th>Entries</th><th>Hours</th></tr></thead>
                            <tbody>
                                @foreach($report['by_employee'] as $name => $agg)
                                    <tr>
                                        <td>{{ $name }}</td>
                                        <td>{{ $agg['entries'] }}</td>
                                        <td>{{ number_format($agg['hours'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="ts-card">
                <h5 style="color:#0b3f90;font-weight:700;">Detailed Log</h5>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Activity</th>
                                <th>Hours</th>
                                <th>Notes</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['rows'] as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->entry_date)->format('M j, Y') }}</td>
                                    <td>{{ $row->employee_name ?: '—' }}</td>
                                    <td>{{ $row->activity_name ?: '—' }}</td>
                                    <td>{{ number_format((float)$row->hours, 2) }}</td>
                                    <td class="text-muted">{{ \Illuminate\Support\Str::limit($row->notes, 50) ?: '—' }}</td>
                                    <td><span class="ts-badge">{{ $row->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No entries in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

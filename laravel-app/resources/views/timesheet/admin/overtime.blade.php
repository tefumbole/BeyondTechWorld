@extends('layout.main')

@section('content')
@php $tsaTab = 'timesheet.admin.overtime'; @endphp
<section class="forms">
    <div class="container-fluid ts-shell">
        @include('timesheet.partials.admin_tabs')

        <div class="mb-4">
            <h1 class="ts-title">Overtime Report</h1>
            <p class="ts-subtitle">Weekly hours vs expected schedule (lunch deducted).</p>
        </div>

        <div class="ts-card mb-4">
            <form method="GET" action="{{ route('timesheet.admin.overtime') }}" class="row align-items-end">
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

        <div class="ts-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Week Starting</th>
                            <th>Logged Hours</th>
                            <th>Expected</th>
                            <th>Overtime</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['employee_name'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($row['week_start'])->format('M j, Y') }}</td>
                                <td>{{ number_format($row['total_hours'], 2) }}h</td>
                                <td>{{ number_format($row['expected_hours'], 2) }}h</td>
                                <td>
                                    @if($row['overtime_hours'] > 0)
                                        <span class="ts-badge" style="background:#fef3c7;color:#92400e;">{{ number_format($row['overtime_hours'], 2) }}h</span>
                                    @else
                                        <span class="text-muted">0.00h</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No overtime data for this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

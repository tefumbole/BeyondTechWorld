@php
    $missingTs = \App\Support\InternCompliance::missingTimesheetDate(Auth::user());
    $weekOk = \App\Support\InternCompliance::workingWeekConfigured(Auth::user());
@endphp
@if(! $weekOk)
    <div class="alert alert-warning">
        Set your <a href="{{ route('timesheet.working-week') }}">working week</a> so tasks and timesheets can follow your schedule.
    </div>
@elseif($missingTs)
    <div class="alert alert-warning">
        Please <a href="{{ route('timesheet.fill', ['date' => $missingTs, 'intern' => 1]) }}">fill your timesheet for {{ $missingTs }}</a> after you finish work. You can still open your task from here.
    </div>
@endif

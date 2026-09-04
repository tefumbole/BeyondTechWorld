@include('timesheet.partials.styles')
@php
    $tsTab = $tsTab ?? '';
    $tabs = [
        ['timesheet.activities', 'Create Activity', 'dripicons-plus', 'tone-blue'],
        ['timesheet.fill', 'Fill Time Sheet', 'dripicons-clock', 'tone-gold'],
        ['timesheet.working-week', 'Working Week', 'dripicons-calendar', 'tone-purple'],
    ];
@endphp
<nav class="ts-nav" aria-label="Employee Timesheet">
    @if(\App\Support\InternCompliance::appliesTo(Auth::user()))
        <a href="{{ url('/admin') }}" class="tone-blue">
            <i class="dripicons-meter"></i> Dashboard
        </a>
        <a href="{{ route('internship.student.dashboard') }}" class="tone-blue">
            <i class="dripicons-graduation"></i> My Task
        </a>
    @elseif(\App\Support\InternCompliance::canSuperviseInternships(Auth::user()) && (int) Auth::user()->role_id > 2)
        <a href="{{ route('internship.supervisor.dashboard') }}" class="tone-blue">
            <i class="dripicons-meter"></i> Dashboard
        </a>
        <a href="{{ route('internship.supervisor.students') }}" class="tone-blue">
            <i class="dripicons-user-group"></i> My Interns
        </a>
    @endif
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $tsTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>

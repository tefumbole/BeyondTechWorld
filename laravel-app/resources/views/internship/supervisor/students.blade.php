@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:8px;">
            <h1 class="ip-title mb-0">{{ \App\Support\InternCompliance::isInternshipAdmin(auth()->user()) ? 'All interns' : 'My interns' }}</h1>
            @include('internship.partials.supervisor_nav', ['ipNavHere' => 'students'])
        </div>
        <p class="ip-meta mb-3">Place each intern on a calendar start date and curriculum day (not necessarily day 1).</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="ip-card">
            <table class="table ip-table">
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Curriculum</th>
                    <th>Progress</th>
                    <th>Starts</th>
                    <th>Status</th>
                    <th>Working week</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($enrolments as $e)
                    <tr>
                        <td>{{ optional($e->student)->name }}</td>
                        <td>{{ optional($e->program)->displayName() }}</td>
                        <td>Day {{ $e->startCurriculumDay() }}–{{ $e->endCurriculumDay() }}</td>
                        <td>{{ $e->completed_count }}/{{ $e->plannedDurationDays() }}</td>
                        <td>{{ $e->start_date ?: '—' }}</td>
                        <td><span class="ip-badge">{{ $e->status }}</span></td>
                        <td>
                            @php $ww = $e->student ? \App\Support\InternCompliance::workingWeekInspect($e->student) : ['configured' => false, 'label' => null]; @endphp
                            @if($ww['configured'])
                                <span class="ip-badge active">Set</span>
                                <div class="ip-meta">{{ $ww['label'] }}</div>
                            @else
                                <span class="ip-badge warn">Missing</span>
                            @endif
                            <div class="mt-1">
                                <a class="ip-btn ip-btn-sm ip-btn-outline" href="{{ route('internship.supervisor.working_week', $e->id) }}">View week</a>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <a class="ip-btn ip-btn-outline" href="{{ route('internship.supervisor.place', $e->id) }}">Place / adjust</a>
                            @if(\App\Support\InternCompliance::isInternshipAdmin(auth()->user()))
                                <form method="POST" action="{{ route('internship.supervisor.destroy', $e->id) }}" class="d-inline"
                                      onsubmit="return confirm('Permanently delete {{ addslashes(optional($e->student)->name ?: 'this intern') }} from the system? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ip-btn ip-btn-outline ip-btn-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No students assigned to you yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $enrolments->links() }}
        </div>
    </div>
</section>
@endsection

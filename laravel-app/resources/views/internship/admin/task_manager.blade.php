@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell" style="max-width:1200px;">
        <a href="{{ route('internship.dashboard') }}" class="ip-btn ip-btn-outline mb-3">&larr; Dashboard</a>
        <h1 class="ip-title">Internship Task Manager</h1>
        <p class="ip-meta mb-3">Track daily task checklist progress and resend WhatsApp task notices to students.</p>

        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="ip-nav">
            @foreach([
                'open' => 'Open tasks',
                'today' => 'Today',
                'available' => 'Available',
                'in_progress' => 'In progress',
                'submitted' => 'Submitted',
                'all' => 'All',
            ] as $key => $label)
                <a class="ip-btn {{ ($status ?? '') === $key ? '' : 'ip-btn-outline' }}"
                   href="{{ route('internship.tasks', ['status' => $key, 'q' => request('q')]) }}">{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('internship.tasks') }}" class="ip-card mb-3">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="d-flex flex-wrap" style="gap:8px;">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px;"
                       placeholder="Search student name or email">
                <button class="ip-btn" type="submit">Search</button>
            </div>
        </form>

        <div class="ip-card">
            <div class="table-responsive">
                <table class="table ip-table mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program / Task</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="min-width:160px;">Checklist</th>
                            <th>WhatsApp</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($assignments as $a)
                        @php
                            $progress = $a->stepProgress();
                            $student = optional($a->enrolment)->student;
                            $program = optional($a->enrolment)->program;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ optional($student)->name ?: '—' }}</strong>
                                <div class="ip-meta">{{ optional($a->enrolment->supervisor)->name ? 'Sup: '.$a->enrolment->supervisor->name : '' }}</div>
                            </td>
                            <td>
                                <div class="ip-meta">{{ $program ? $program->displayName() : '—' }}</div>
                                <strong>#{{ $a->progression_day }} — {{ optional($a->task)->title ?: 'Task' }}</strong>
                            </td>
                            <td>{{ optional($a->scheduled_work_date)->format('d M Y') ?: '—' }}</td>
                            <td><span class="ip-badge">{{ str_replace('_', ' ', $a->status) }}</span></td>
                            <td>
                                <div class="ip-progress-wrap">
                                    <div class="ip-progress-bar"><span style="width:{{ $progress['percent'] }}%;"></span></div>
                                    <div class="ip-progress-label">{{ $progress['done'] }}/{{ $progress['total'] }} steps ({{ $progress['percent'] }}%)</div>
                                </div>
                            </td>
                            <td class="ip-meta">
                                @if($a->whatsapp_sent_at)
                                    Sent {{ optional($a->whatsapp_sent_at)->format('d M H:i') }}
                                @else
                                    Not sent
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <form method="POST" action="{{ route('internship.tasks.resend', $a->id) }}" class="d-inline"
                                      onsubmit="return confirm('Resend this daily task WhatsApp to {{ addslashes(optional($student)->name ?: 'the student') }}?');">
                                    @csrf
                                    <input type="hidden" name="include_supervisors" value="1">
                                    <button type="submit" class="ip-btn ip-btn-sm" title="Resend WhatsApp">
                                        <i class="dripicons-clockwise"></i> Resend
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No tasks match this filter.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $assignments->links() }}</div>
        </div>
    </div>
</section>
@endsection

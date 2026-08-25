@php
    $awaitingCount = isset($awaitingGradingCount) ? (int) $awaitingGradingCount : (isset($awaiting) ? $awaiting->count() : 0);
    $nav = $activeNav ?? '';
@endphp
<div class="ip-nav">
    <a class="ip-btn {{ $nav === 'task' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.student.dashboard') }}">
        <i class="dripicons-graduation"></i> My Task
    </a>
    @if(!empty($assignment) && empty($hideOpenTask))
        <a class="ip-btn" href="{{ route('internship.student.task', $assignment->id) }}">
            <i class="dripicons-document-edit"></i>
            @if($assignment->status === 'submitted') View submission
            @elseif($assignment->status === 'revision_required') Fix and re-upload
            @else Open my task
            @endif
        </a>
    @endif
    <a class="ip-btn {{ $nav === 'upload' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.student.upload') }}">
        <i class="dripicons-upload"></i> Upload Task
        @if($awaitingCount > 0)
            <span class="ip-nav-count">{{ $awaitingCount }}</span>
        @endif
    </a>
    <a class="ip-btn {{ $nav === 'messages' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.student.messages') }}">
        <i class="dripicons-message"></i> Message supervisor
    </a>
    <a class="ip-btn {{ $nav === 'portfolio' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.student.portfolio') }}">
        <i class="dripicons-folder"></i> Portfolio
    </a>
</div>

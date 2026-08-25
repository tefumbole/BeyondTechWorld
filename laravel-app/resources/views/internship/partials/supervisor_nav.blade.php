@php
    $ipNavHere = $ipNavHere ?? '';
    $pendingGrades = $pendingGrades ?? ($stats['pending_grades'] ?? 0);
    $ipActiveMap = [
        'home' => ['ip-sup-home', 'ip-admin-sup-home'],
        'students' => ['ip-my-students'],
        'interns' => ['ip-interns', 'ip-sup-interns'],
        'tasks' => ['ip-tasks-sup', 'ip-admin-tasks'],
        'queue' => ['ip-grade-queue', 'ip-admin-queue'],
        'dashboard' => ['ip-hub'],
    ];
    $ipActiveIds = $ipActiveIds ?? ($ipActiveMap[$ipNavHere] ?? []);
@endphp
<div class="ip-nav mb-0">
    <a class="ip-btn {{ $ipNavHere === 'home' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.supervisor.dashboard') }}">Supervisor Home</a>
    <a class="ip-btn {{ $ipNavHere === 'students' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.supervisor.students') }}">
        {{ \App\Support\InternCompliance::isInternshipAdmin(auth()->user()) ? 'All Interns' : 'My Interns' }}
    </a>
    @if(\App\Support\InternCompliance::isInternshipAdmin(auth()->user()))
        <a class="ip-btn {{ $ipNavHere === 'interns' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.interns') }}">Interns</a>
    @endif
    <a class="ip-btn {{ $ipNavHere === 'tasks' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.tasks') }}">Task Manager</a>
    <a class="ip-btn {{ $ipNavHere === 'queue' ? '' : 'ip-btn-outline' }}" href="{{ route('internship.supervisor.index') }}">
        Grade Queue
        @if($pendingGrades > 0)
            <span class="beyond-attention-badge">{{ $pendingGrades }}</span>
        @endif
    </a>
</div>
<script>
(function () {
    var ids = @json($ipActiveIds);
    ids.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('active');
    });
})();
</script>

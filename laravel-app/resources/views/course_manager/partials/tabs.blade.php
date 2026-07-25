@include('course_manager.partials.styles')
@php
    $cmTab = $cmTab ?? '';
    $tabs = [
        ['courses.index', 'Course List', 'dripicons-view-list', 'tone-blue'],
        ['courses.create', 'Add Course', 'dripicons-plus', 'tone-gold'],
        ['courses.registrations', 'Registrations', 'dripicons-user-group', 'tone-purple'],
        ['courses.invoices', 'Invoices', 'dripicons-document', 'tone-teal'],
        ['courses.certificates', 'Certificates', 'dripicons-trophy', 'tone-orange'],
        ['courses.progress', 'Student Progress', 'dripicons-graph-line', 'tone-green'],
        ['courses.feedback', 'Feedback', 'dripicons-message', 'tone-red'],
    ];
@endphp
<nav class="cm-nav" aria-label="Course Management">
    @foreach($tabs as $tab)
        <a href="{{ route($tab[0]) }}" class="{{ $tab[3] }} {{ $cmTab === $tab[0] ? 'is-active' : '' }}">
            <i class="{{ $tab[2] }}"></i> {{ $tab[1] }}
        </a>
    @endforeach
</nav>

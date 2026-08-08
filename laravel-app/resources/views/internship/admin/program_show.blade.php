@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <a href="{{ route('internship.programs') }}" class="ip-btn ip-btn-outline mb-3">&larr; Programs</a>
        <h1 class="ip-title">{{ $program->name }}</h1>
        <p class="ip-meta">{{ $program->code }} v{{ $program->version }} · {{ $program->status }} · {{ $program->tasks->count() }} tasks</p>
        <div class="ip-card">
            <table class="table ip-table table-sm">
                <thead><tr><th>#</th><th>Title</th><th>Hours</th><th>Pass</th></tr></thead>
                <tbody>
                @foreach($program->tasks as $t)
                    <tr>
                        <td>{{ $t->day_number }}</td>
                        <td>{{ $t->title }}</td>
                        <td>{{ $t->estimated_hours }}</td>
                        <td>{{ $t->pass_mark }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

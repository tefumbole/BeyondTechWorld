@extends('layout.main')
@section('content')
@include('internship.partials.styles')
<section class="forms">
    <div class="container-fluid ip-shell">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h1 class="ip-title mb-1">Internship Programs</h1>
                <p class="ip-meta mb-0">Open a track to view and edit its full day-by-day curriculum (up to 180 days).</p>
            </div>
            <a href="{{ route('internship.import') }}" class="ip-btn ip-btn-outline"><i class="dripicons-cloud-upload"></i> Import Curriculum</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="ip-card">
            <table class="table ip-table">
                <thead>
                <tr>
                    <th>Program</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Days</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($programs as $p)
                    <tr>
                        <td>
                            <strong style="color:#0b3f90;">{{ $p->displayName() }}</strong>
                            @if($p->discipline)
                                <div class="ip-meta">{{ $p->discipline }}</div>
                            @endif
                        </td>
                        <td>{{ $p->code }}</td>
                        <td><span class="ip-badge {{ $p->status === 'published' ? 'active' : '' }}">{{ $p->status }}</span></td>
                        <td>{{ $p->tasks_count }}</td>
                        <td class="text-right">
                            <a class="ip-btn" href="{{ route('internship.programs.show', $p->id) }}">View &amp; edit days</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No programs yet. Use <strong>Import Curriculum</strong> to load the 180-day bank.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

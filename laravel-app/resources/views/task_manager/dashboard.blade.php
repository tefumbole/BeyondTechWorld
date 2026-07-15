@extends('layout.main')

@section('content')
@php $tmTab = 'tasks.dashboard'; @endphp
<section class="forms">
    <div class="container-fluid">
        @include('task_manager.partials.tabs')
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;">Task Dashboard</h3>
                <p class="text-muted mb-0">Overview of team assignments — click a stat to open that list.</p>
            </div>
            <div>
                <a href="{{ route('tasks.dashboard') }}" class="btn btn-outline-secondary btn-sm">Refresh</a>
                @if(in_array('tasks.create', $all_permission ?? []))
                    <a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><i class="dripicons-plus"></i> New Task</a>
                @endif
            </div>
        </div>

        <div class="row">
            @foreach([
                ['Total Tasks', $stats['total'], 'primary', route('tasks.index')],
                ['Pending', $stats['pending'], 'secondary', route('tasks.pending')],
                ['In Progress', $stats['in_progress'], 'warning', route('tasks.index', ['status' => 'In Progress'])],
                ['Completed', $stats['completed'], 'success', route('tasks.index', ['status' => 'Completed'])],
                ['Overdue', $stats['overdue'], 'danger', route('tasks.index')],
            ] as $card)
                <div class="col-md-4 col-xl mb-3">
                    <a href="{{ $card[3] }}" class="text-decoration-none">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">{{ $card[0] }}</div>
                                <div class="display-4 font-weight-bold text-{{ $card[2] }}">{{ $card[1] }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body">
                <h5 style="color:#0b3f90;">Status Distribution</h5>
                <p class="mb-0">Total active workload: <strong>{{ $stats['open'] }} open tasks</strong>.</p>
                <div class="progress mt-3" style="height:28px;">
                    @php
                        $total = max(1, $stats['pending'] + $stats['in_progress'] + $stats['completed'] + $stats['overdue']);
                    @endphp
                    <div class="progress-bar bg-secondary" style="width:{{ round(100*$stats['pending']/$total) }}%">Pending {{ $stats['pending'] }}</div>
                    <div class="progress-bar bg-warning" style="width:{{ round(100*$stats['in_progress']/$total) }}%">In Progress {{ $stats['in_progress'] }}</div>
                    <div class="progress-bar bg-success" style="width:{{ round(100*$stats['completed']/$total) }}%">Completed {{ $stats['completed'] }}</div>
                    <div class="progress-bar bg-danger" style="width:{{ round(100*$stats['overdue']/$total) }}%">Overdue {{ $stats['overdue'] }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

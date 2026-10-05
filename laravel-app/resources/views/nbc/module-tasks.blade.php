@extends('nbc.layout')
@section('title', 'Tasks · Praise Team')
@section('content')
    <p class="kicker">Task manager</p>
    <h1>Tasks</h1>
    <form class="card" method="POST" action="{{ route('nbc.tasks.store') }}">
        @csrf
        <label for="title">Title</label>
        <input id="title" name="title" required>
        <label for="body">Details</label>
        <textarea id="body" name="body"></textarea>
        <label for="assignee_id">Assign to</label>
        <select id="assignee_id" name="assignee_id">
            <option value="">Unassigned</option>
            @foreach($people as $person)
                <option value="{{ $person->id }}">{{ $person->name }}</option>
            @endforeach
        </select>
        <label for="due_on">Due</label>
        <input id="due_on" name="due_on" type="date">
        <button type="submit">Save task</button>
    </form>
    @foreach($tasks as $task)
        <div class="row">
            <div class="num">{{ $task->status === 'done' ? 'Done' : 'Open' }}</div>
            <div>
                <strong>{{ $task->title }}</strong>
                <div class="sub">{{ $task->assignee ? $task->assignee->name : 'Unassigned' }} @if($task->due_on) · due {{ $task->due_on->format('j M Y') }} @endif</div>
                <div>{{ $task->body }}</div>
                <form method="POST" action="{{ route('nbc.tasks.close', $task->id) }}">@csrf<button type="submit">{{ $task->status === 'done' ? 'Reopen' : 'Mark done' }}</button></form>
            </div>
        </div>
    @endforeach
@endsection

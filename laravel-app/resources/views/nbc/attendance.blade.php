@extends('nbc.layout')
@section('title', 'Attendance · Praise Team')
@section('content')
    <p class="kicker">Rehearsal</p>
    <h1>Attendance</h1>
    @if($member->allows('nbc.attendance.self'))
        @if($open)
            <form method="POST" action="{{ route('nbc.attendance.out') }}">
                @csrf
                <p>Clocked in {{ $open->clock_in->format('D j M, g:i a') }}.</p>
                <button type="submit">Clock out</button>
            </form>
        @else
            <form method="POST" action="{{ route('nbc.attendance.in') }}">
                @csrf
                <label for="event_id">Practice</label>
                <select id="event_id" name="event_id">
                    <option value="">No specific practice</option>
                    @foreach($practices as $practice)
                        <option value="{{ $practice->id }}">{{ $practice->title }} {{ $practice->starts_at ? $practice->starts_at->format('j M g:i a') : '' }}</option>
                    @endforeach
                </select>
                <button type="submit">Clock in</button>
            </form>
        @endif
        <h2>Mine</h2>
        @foreach($mine as $row)
            <div class="row">
                <div class="num"></div>
                <div>{{ $row->clock_in->format('D j M, g:i a') }} @if($row->clock_out) — {{ $row->clock_out->format('g:i a') }} @else — still in @endif</div>
            </div>
        @endforeach
    @endif
    @if($member->allows('nbc.attendance.review'))
        <h2>Team</h2>
        <table>
            <tr><th>Member</th><th>In</th><th>Out</th></tr>
            @foreach($all as $row)
                <tr>
                    <td>{{ $row->member ? $row->member->name : '' }}</td>
                    <td>{{ $row->clock_in->format('j M g:i a') }}</td>
                    <td>{{ $row->clock_out ? $row->clock_out->format('g:i a') : 'In' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection

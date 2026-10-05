@extends('nbc.layout')
@section('title', 'People · Praise Team')
@section('content')
    <p class="kicker">Roster</p>
    <h1>People</h1>
    @foreach($people as $person)
        <form class="card" method="POST" action="{{ route('nbc.people.save', $person->id) }}">
            @csrf
            <strong>{{ $person->name }}</strong>
            <div class="sub">{{ $person->email }} @if($person->part) · {{ $person->part }} @endif</div>
            <label>Role</label>
            <select name="role">
                @foreach(['member', 'leader', 'owner'] as $role)
                    <option value="{{ $role }}" {{ $person->role === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
            <label>Status</label>
            <select name="status">
                @foreach(['pending', 'active', 'declined'] as $status)
                    <option value="{{ $status }}" {{ $person->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            @if($nbcMember->allows('nbc.permissions'))
                <div class="checks">
                    @foreach($keys as $key => $label)
                        <label><input type="checkbox" name="permissions[]" value="{{ $key }}" {{ in_array($key, $person->permissionList(), true) ? 'checked' : '' }}> {{ $label }}</label>
                    @endforeach
                </div>
            @endif
            <button type="submit">Save</button>
        </form>
    @endforeach
@endsection

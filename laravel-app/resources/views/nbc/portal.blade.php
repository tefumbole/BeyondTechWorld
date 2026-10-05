@extends('nbc.layout')
@section('title', 'Portal · Praise Team')
@section('content')
    <p class="kicker">{{ ucfirst($member->role) }}</p>
    <h1>{{ $member->name }}</h1>
    <p class="sub">Nkwen Baptist Church Praise Team. These are the services your permissions open.</p>
    @if($openAttendance)
        <div class="alert">You are clocked in since {{ $openAttendance->clock_in->format('g:i a') }}.</div>
    @endif
    <div class="modules">
        @foreach($modules as $module)
            <a class="module" href="{{ route($module['route']) }}">{{ $module['label'] }}</a>
        @endforeach
    </div>
@endsection

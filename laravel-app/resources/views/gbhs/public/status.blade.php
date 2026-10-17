@extends('gbhs.public.layout')
@section('content')
<h1>Application status</h1>
<form class="card" method="post" action="{{ route('gbhs.status.send') }}">
    @csrf
    <label>Reference</label><input name="reference" required>
    <label>Phone used on the application</label><input name="phone" required>
    <button type="submit">Send code</button>
</form>
@if(session('gbhs_status_phone'))
<form class="card" method="post" action="{{ route('gbhs.status.check') }}">
    @csrf
    <label>Code</label><input name="otp" required>
    <button type="submit">Show status</button>
</form>
@endif
@if(!empty($application))
    <div class="card">
        <p>{{ $application->reference }} · {{ $application->status }}</p>
        @if(!empty($member))
            <p>Membership number {{ $member->membership_number }}</p>
            <a href="{{ route('gbhs.member') }}">Open member home</a>
        @endif
    </div>
@endif
@endsection

@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Alumni Members</h3>
    @include('gbhs.admin.nav')
    <table class="table">
        <tr><th>Number</th><th>Status</th><th>Approved</th></tr>
        @foreach($members as $member)
            <tr><td>{{ $member->membership_number }}</td><td>{{ $member->status }}</td><td>{{ $member->approved_at }}</td></tr>
        @endforeach
    </table>
</div></section>
@endsection

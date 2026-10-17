@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>{{ $application->reference }}</h3>
    @include('gbhs.admin.nav')
    <div class="card p-3">
        <p>{{ $application->full_name }} · {{ $application->phone }} · {{ $application->status }}</p>
        <p>{{ $application->profession }} {{ $application->city }} {{ $application->country }}</p>
        @if($bylaw)<p>Bylaws {{ $bylaw->version }}</p>@endif
        @if($application->signature_path && (in_array('gbhs.manage', $all_permission) || in_array('gbhs.membership', $all_permission)))
            <p>Signature</p>
            <img src="{{ url('public/'.$application->signature_path) }}" alt="Signature" style="max-width:280px">
        @endif
        @if($member)<p>Membership number {{ $member->membership_number }}</p>@endif
    </div>
    @if(in_array('gbhs.manage', $all_permission) || in_array('gbhs.membership', $all_permission))
    <form class="card p-3 mt-2" method="post" action="{{ route('gbhs.applications.review', $application->id) }}">
        @csrf
        <label>Note</label>
        <textarea class="form-control" name="review_note">{{ $application->review_note }}</textarea>
        <select class="form-control mt-2" name="decision">
            <option value="under_review">Under review</option>
            <option value="more_info">Ask for more information</option>
            <option value="approved">Approve</option>
            <option value="rejected">Reject</option>
        </select>
        <button class="btn btn-primary mt-2" type="submit">Save decision</button>
    </form>
    @endif
</div></section>
@endsection

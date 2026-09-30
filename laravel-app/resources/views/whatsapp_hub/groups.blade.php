@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">WhatsApp Groups</h1>
        <p class="wa-sub">Every group on this WhatsApp account, with the number of members in each one.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        @if(!empty($listError))<div class="alert alert-danger">{{ $listError }}</div>@endif
        <p class="mb-3">
            <a class="btn btn-primary" href="{{ route('whatsapp.groups.export') }}">Download all group contacts (CSV)</a>
        </p>
        <div class="wa-card table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Group</th><th>Members</th></tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    <tr>
                        <td>{{ $group['name'] }}</td>
                        <td>{{ number_format($group['members']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No groups were returned for this WhatsApp account.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

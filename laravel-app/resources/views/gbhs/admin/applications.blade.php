@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Membership Applications</h3>
    @include('gbhs.admin.nav')
    <table class="table">
        <tr><th>Reference</th><th>Name</th><th>Phone</th><th>Status</th></tr>
        @foreach($applications as $application)
            <tr>
                <td><a href="{{ route('gbhs.applications.show', $application->id) }}">{{ $application->reference }}</a></td>
                <td>{{ $application->full_name }}</td>
                <td>{{ $application->phone }}</td>
                <td>{{ $application->status }}</td>
            </tr>
        @endforeach
    </table>
</div></section>
@endsection

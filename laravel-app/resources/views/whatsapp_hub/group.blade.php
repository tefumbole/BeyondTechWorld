@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <p class="mb-2"><a href="{{ route('whatsapp.groups') }}">All groups</a></p>
        <h1 class="wa-title">{{ $groupName }}</h1>
        <p class="wa-sub">{{ number_format(count($contacts)) }} {{ count($contacts) === 1 ? 'contact' : 'contacts' }}</p>
        @if(!empty($listError))<div class="alert alert-danger">{{ $listError }}</div>@endif
        <div class="wa-card table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Phone</th></tr>
                </thead>
                <tbody>
                @forelse($contacts as $contact)
                    @php
                        $name = trim((string) $contact['name']);
                        $phone = trim((string) $contact['phone']);
                    @endphp
                    <tr>
                        <td>{{ $name !== '' ? $name : ($phone !== '' ? $phone : 'No name') }}</td>
                        <td>{{ $phone !== '' ? $phone : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No contacts were returned for this group.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

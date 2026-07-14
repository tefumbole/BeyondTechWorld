@extends('layout.main')

@section('content')
<section class="forms booking-create-page">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#0b3f90,#1456b8);color:#fff;">
                <h4 class="mb-0"><i class="dripicons-document-edit"></i> Edit Event — {{ $event->name }}</h4>
                <a href="{{ route('events.show', $event->id) }}" class="btn btn-sm btn-light">Back to event</a>
            </div>
            <div class="card-body" style="background:#f8fbff;">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                @include('events.partials.form', ['event' => $event, 'customers' => $customers, 'bookings' => $bookings, 'workerProfiles' => $workerProfiles])
            </div>
        </div>
    </div>
</section>
@endsection

@stack('scripts')

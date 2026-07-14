@extends('layout.main')

@section('content')
<style>
    .booking-section { background:#fff;border:1px solid #e3e9f4;border-radius:14px;padding:20px;margin-bottom:18px; }
    .booking-section-title { font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#0b3f90;margin-bottom:14px; }
</style>
<section class="forms">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:linear-gradient(135deg,#0b3f90,#1456b8);color:#fff;">
                <h4 class="mb-0"><i class="dripicons-plus"></i> Create Event</h4>
                <a href="{{ route('events.index') }}" class="btn btn-sm btn-light">All Events</a>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                @include('events.partials.form', ['event' => $event, 'customers' => $customers, 'bookings' => $bookings])
            </div>
        </div>
    </div>
</section>
@endsection

@stack('scripts')

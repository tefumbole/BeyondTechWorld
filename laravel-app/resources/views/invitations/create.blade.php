@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="dripicons-plus"></i> Create Digital Invitation</h4>
            <a href="{{ route('invitations.index') }}" class="btn btn-default">Back</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('invitations.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Event *</label>
                        <select name="event_id" class="form-control" required>
                            <option value="">Select event</option>
                            @foreach($events as $ev)
                                <option value="{{ $ev->id }}" {{ old('event_id') == $ev->id ? 'selected' : '' }}>
                                    {{ $ev->title }}
                                    @if($ev->event_date) — {{ \Carbon\Carbon::parse($ev->event_date)->format('d M Y') }} @endif
                                </option>
                            @endforeach
                        </select>
                        @if($events->isEmpty())
                            <small class="text-muted">No invitation events found in the data store. Create an event in the legacy events list first, or add one to the <code>events</code> table.</small>
                        @endif
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Guest name *</label>
                            <input type="text" name="guest_name" class="form-control" value="{{ old('guest_name') }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Phone *</label>
                            <input type="text" name="guest_phone" class="form-control" value="{{ old('guest_phone') }}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Email</label>
                            <input type="email" name="guest_email" class="form-control" value="{{ old('guest_email') }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Invitation type</label>
                            <select name="invitation_type" class="form-control">
                                @foreach(['Standard','VIP','Government','Speaker','Media'] as $type)
                                    <option value="{{ $type }}" {{ old('invitation_type', 'Standard') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" @if($events->isEmpty()) disabled @endif>Create Invitation</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="dripicons-checkmark"></i> Invitation Check-in</h4>
            <a href="{{ route('invitations.index') }}" class="btn btn-default">Back</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif

        <div class="card" style="max-width:520px;">
            <div class="card-body">
                <form method="POST" action="{{ route('invitations.check_in.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Invitation code *</label>
                        <input type="text" name="code" class="form-control form-control-lg" placeholder="EVENT-2026-12345" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Check in guest</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

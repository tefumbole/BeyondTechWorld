@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h4 class="mb-0"><i class="dripicons-ticket"></i> Invitation {{ $invitation->qr_code }}</h4>
            <a href="{{ route('invitations.index') }}" class="btn btn-default">Back to list</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-warning">{{ session('not_permitted') }}</div>
        @endif

        <div class="row">
            <div class="col-md-7">
                <div class="card mb-3">
                    <div class="card-body">
                        <p><strong>Guest:</strong> {{ $invitation->displayName() }}</p>
                        <p><strong>Phone:</strong> {{ $invitation->displayPhone() ?: '—' }}</p>
                        <p><strong>Email:</strong> {{ $invitation->guest_email ?: optional($invitation->guest)->email ?: '—' }}</p>
                        <p><strong>Event:</strong> {{ optional($invitation->event)->title ?: '—' }}</p>
                        <p><strong>Type:</strong> {{ $invitation->invitation_type ?: 'Standard' }}</p>
                        <p><strong>Status:</strong> {{ $invitation->status }}</p>
                        <p><strong>Checked in:</strong> {{ $invitation->checked_in ? 'Yes' : 'No' }}
                            @if($invitation->checked_in_at) <small class="text-muted">({{ $invitation->checked_in_at }})</small> @endif
                        </p>
                        <p><strong>Code:</strong> <code>{{ $invitation->qr_code }}</code></p>
                    </div>
                </div>
                @if(in_array('invitations.delete', $all_permission))
                    <form method="POST" action="{{ route('invitations.destroy', $invitation->id) }}" onsubmit="return confirm('Delete this invitation?');">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                @endif
            </div>
            <div class="col-md-5">
                <div class="card text-center">
                    <div class="card-body">
                        <p class="text-muted mb-2">QR code</p>
                        <?php
                            echo '<img src="data:image/png;base64,'.DNS2D::getBarcodePNG($invitation->qr_code, 'QRCODE').'" height="180" width="180" alt="qrcode">';
                        ?>
                        <div class="mt-2"><code>{{ $invitation->qr_code }}</code></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@extends('layout.main')
@section('content')
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message') !!}</div>
@endif
@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Review & Countersign — {{ $booking->reference_no }}</h4>
                <a href="{{ route('booking.pending-review') }}" class="btn btn-sm btn-secondary">Back to Pending Review</a>
            </div>
            <div class="card-body">
                @include('booking.partials.contract_document', [
                    'contract' => $contract,
                    'booking' => $booking,
                    'general_setting' => $general_setting,
                    'items' => $items,
                    'header' => $header,
                    'footer' => $footer,
                    'clientSignatureSrc' => $clientSignatureSrc,
                    'adminSignatureSrc' => null,
                ])

                <hr>
                <form method="POST" action="{{ route('booking.contract.approve', $contract->id) }}" id="admin-sign-form">
                    @csrf
                    <h5 class="mb-3" style="color:#0b3f90;">Admin Countersignature</h5>
                    <p class="text-muted">Sign below to approve this contract. The client and booking creator will receive the final PDF and QR code via WhatsApp.</p>
                    <div class="signature-pad-wrap mb-3" style="border:2px dashed #0b3f90;border-radius:12px;background:#f8fbff;padding:12px;">
                        <canvas id="admin-signature-pad"></canvas>
                    </div>
                    <input type="hidden" name="admin_signature_image" id="admin_signature_image">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary mr-2" id="clear-admin-signature">Clear</button>
                        <button type="submit" class="btn btn-primary"><i class="dripicons-checkmark"></i> Approve & Send to Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<style>
    #admin-signature-pad {
        display: block;
        width: 100%;
        max-width: 600px;
        height: 180px;
        background: #fff;
        border-radius: 8px;
        touch-action: none;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('admin-signature-pad');
    var clearBtn = document.getElementById('clear-admin-signature');
    var form = document.getElementById('admin-sign-form');
    if (!canvas || !clearBtn || !form) {
        return;
    }

    var PAD_HEIGHT = 180;
    var signaturePad = null;

    function initSignaturePad() {
        if (typeof SignaturePad === 'undefined') {
            return;
        }

        signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });

        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var width = canvas.parentElement.clientWidth || 600;
        canvas.style.width = width + 'px';
        canvas.style.height = PAD_HEIGHT + 'px';
        canvas.width = width * ratio;
        canvas.height = PAD_HEIGHT * ratio;
        var ctx = canvas.getContext('2d');
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(ratio, ratio);
        signaturePad.clear();
    }

    window.addEventListener('load', initSignaturePad);

    clearBtn.addEventListener('click', function () {
        if (signaturePad) {
            signaturePad.clear();
        }
    });

    form.addEventListener('submit', function (e) {
        if (!signaturePad || signaturePad.isEmpty()) {
            e.preventDefault();
            alert('Please provide your signature before approving.');
            return false;
        }
        document.getElementById('admin_signature_image').value = signaturePad.toDataURL('image/png');
    });
})();
</script>
@endsection

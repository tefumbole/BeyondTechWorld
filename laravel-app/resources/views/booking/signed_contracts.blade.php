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
            <div class="card-header">
                <h4 class="mb-0">Signed Contracts</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Approved</th>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>Created By</th>
                                <th>Grand Total</th>
                                <th class="not-exported">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contracts as $contract)
                                @php $booking = $contract->booking; @endphp
                                <tr>
                                    <td>{{ ($contract->approved_at ?? $contract->signed_at) ? ($contract->approved_at ?? $contract->signed_at)->format('d M Y, H:i') : '-' }}</td>
                                    <td>{{ $booking->reference_no ?? '-' }}</td>
                                    <td>{{ optional($booking->customer)->name ?? '-' }}</td>
                                    <td>{{ optional($booking->user)->name ?? '-' }}</td>
                                    <td>{{ $booking ? number_format($booking->grand_total, 2) : '-' }}</td>
                                    <td>
                                        <a href="{{ route('booking.contract.view', $contract->id) }}" class="btn btn-sm btn-info"><i class="dripicons-preview"></i> View Contract</a>
                                        @if($contract->signed_pdf_path)
                                            <a href="{{ url($contract->signed_pdf_path) }}" target="_blank" class="btn btn-sm btn-primary"><i class="dripicons-download"></i> PDF</a>
                                        @endif
                                        @if($booking)
                                            <a href="{{ url('bookings/gen_invoice/' . $booking->id) }}" class="btn btn-sm btn-secondary"><i class="dripicons-document"></i> Receipt</a>
                                        @endif
                                        @if($contract->qr_token && $contract->isApproved())
                                            <a href="{{ route('rental.scan', $contract->qr_token) }}" target="_blank" class="btn btn-sm btn-secondary"><i class="fa fa-qrcode"></i> QR</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No signed contracts yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    $("ul#booking #booking-signed-menu").addClass("active");
    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
</script>
@endsection

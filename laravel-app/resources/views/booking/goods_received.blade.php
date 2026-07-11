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
                <h4 class="mb-0">Goods Received</h4>
                <a href="{{ route('booking.index') }}" class="btn btn-primary btn-sm"><i class="dripicons-list"></i> Booking List</a>
            </div>
            <div class="card-body">
                <p class="text-muted">Generate a goods delivery note from Booking List, then send for signature so the client confirms receipt of equipment.</p>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Delivery Ref</th>
                                <th>Booking Ref</th>
                                <th>Customer</th>
                                <th>WhatsApp</th>
                                <th>Status</th>
                                <th>Sent</th>
                                <th class="not-exported">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipts as $receipt)
                                @php $booking = $receipt->booking; @endphp
                                <tr>
                                    <td>{{ $receipt->created_at->format('d M Y') }}</td>
                                    <td>{{ $receipt->reference_no }}</td>
                                    <td>{{ optional($booking)->reference_no ?? '-' }}</td>
                                    <td>{{ optional(optional($booking)->customer)->name ?? '-' }}</td>
                                    <td>
                                        @if(optional(optional($booking)->customer)->phone_number)
                                            {{ \App\Support\WhatsAppPhone::display($booking->customer->phone_number) }}
                                        @else
                                            <span class="text-danger">Missing</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($receipt->signed_at)
                                            <span class="badge badge-success">Received {{ $receipt->signed_at->format('d M Y') }}</span>
                                        @elseif($receipt->signature_sent_at)
                                            <span class="badge badge-warning">Awaiting Client</span>
                                        @else
                                            <span class="badge badge-secondary">Draft</span>
                                        @endif
                                        @if($receipt->delivered_signed_at)
                                            <span class="badge badge-info d-block mt-1">Delivered {{ $receipt->delivered_signed_at->format('d M Y') }}</span>
                                        @elseif($receipt->delivered_signature_sent_at)
                                            <span class="badge badge-light d-block mt-1">Awaiting CC (delivered)</span>
                                        @endif
                                    </td>
                                    <td>{{ $receipt->signature_sent_at ? $receipt->signature_sent_at->format('d M Y, H:i') : '-' }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('booking.goods-received.delivery-note', $receipt->id) }}" class="btn btn-sm btn-default" target="_blank"><i class="fa fa-file-pdf-o"></i> Delivery Note</a>
                                            @if(!$receipt->signed_at)
                                                <form action="{{ route('booking.goods-received.send-signature', $receipt->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-info" title="Send for signature"><i class="fa fa-whatsapp"></i> Send for Signature</button>
                                                </form>
                                                @if($receipt->signature_sent_at)
                                                    <form action="{{ route('booking.goods-received.resend', $receipt->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-secondary"><i class="fa fa-refresh"></i> Resend</button>
                                                    </form>
                                                @endif
                                            @else
                                                <a href="{{ route('booking.goods-received.signed-pdf', $receipt->id) }}" class="btn btn-sm btn-success" target="_blank"><i class="fa fa-check"></i> Signed PDF</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No goods received records yet. Use <strong>Generate Goods Delivery Note</strong> from Booking List.</td>
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
    $("ul#booking #booking-goods-received-menu").addClass("active");
    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
</script>
@endsection

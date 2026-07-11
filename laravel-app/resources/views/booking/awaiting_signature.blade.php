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
                <h4 class="mb-0">Awaiting Signature</h4>
                <a href="{{ route('booking.create') }}" class="btn btn-primary btn-sm"><i class="dripicons-plus"></i> New Booking</a>
            </div>
            <div class="card-body">
                <p class="text-muted">Bookings sent for signature stay here until the client signs. No receipt is generated until signing is complete.</p>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>WhatsApp</th>
                                <th>Created By</th>
                                <th>Grand Total</th>
                                <th>Sent</th>
                                <th class="not-exported">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contracts as $contract)
                                @php $booking = $contract->booking; @endphp
                                <tr>
                                    <td>{{ optional($booking)->created_at ? $booking->created_at->format('d M Y') : '-' }}</td>
                                    <td>{{ optional($booking)->reference_no ?? '-' }}</td>
                                    <td>{{ optional(optional($booking)->customer)->name ?? '-' }}</td>
                                    <td>
                                        @if(optional(optional($booking)->customer)->phone_number)
                                            {{ \App\Support\WhatsAppPhone::display($booking->customer->phone_number) }}
                                        @else
                                            <span class="text-danger">Missing</span>
                                        @endif
                                    </td>
                                    <td>{{ optional(optional($booking)->user)->name ?? '-' }}</td>
                                    <td>{{ $booking ? number_format($booking->grand_total, 2) : '-' }}</td>
                                    <td>{{ $contract->created_at->format('d M Y, H:i') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <form action="{{ route('booking.contract.resend', $contract->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-info" title="Resend signature link"><i class="fa fa-whatsapp"></i> Resend</button>
                                            </form>
                                            @if($booking)
                                                <a href="{{ route('booking.edit', $booking->id) }}" class="btn btn-sm btn-warning"><i class="dripicons-document-edit"></i> Edit</a>
                                            @endif
                                            <form action="{{ route('booking.contract.destroy', $contract->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this awaiting-signature booking?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="dripicons-trash"></i> Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No bookings awaiting signature.</td>
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
    $("ul#booking #booking-awaiting-menu").addClass("active");
    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
</script>
@endsection

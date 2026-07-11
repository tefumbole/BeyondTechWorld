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
                <h4 class="mb-0">Pending Review</h4>
                <span class="text-muted small">Client signed — awaiting admin countersignature</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Client Signed</th>
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
                                    <td>{{ $contract->signed_at ? $contract->signed_at->format('d M Y, H:i') : '-' }}</td>
                                    <td>{{ $booking->reference_no ?? '-' }}</td>
                                    <td>{{ optional($booking->customer)->name ?? '-' }}</td>
                                    <td>{{ optional($booking->user)->name ?? '-' }}</td>
                                    <td>{{ $booking ? number_format($booking->grand_total, 2) : '-' }}</td>
                                    <td>
                                        <a href="{{ route('booking.contract.view', $contract->id) }}" class="btn btn-sm btn-info"><i class="dripicons-preview"></i> View</a>
                                        <a href="{{ route('booking.contract.review', $contract->id) }}" class="btn btn-sm btn-primary"><i class="dripicons-pencil"></i> Review & Sign</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No contracts pending review.</td>
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
    $("ul#booking #booking-pending-menu").addClass("active");
    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
</script>
@endsection

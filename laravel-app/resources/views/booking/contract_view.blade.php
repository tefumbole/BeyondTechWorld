@extends('layout.main')
@section('content')
<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h4 class="mb-0">Signed Contract — {{ $booking->reference_no }}</h4>
                <div>
                    @if($contract->signed_pdf_path)
                        <a href="{{ url($contract->signed_pdf_path) }}" target="_blank" class="btn btn-sm btn-primary"><i class="dripicons-download"></i> Download PDF</a>
                    @endif
                    @if($contract->isPendingReview())
                        <a href="{{ route('booking.contract.review', $contract->id) }}" class="btn btn-sm btn-warning"><i class="dripicons-pencil"></i> Review & Sign</a>
                    @endif
                    <a href="{{ url()->previous() }}" class="btn btn-sm btn-secondary">Back</a>
                </div>
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
                    'adminSignatureSrc' => $adminSignatureSrc,
                ])
            </div>
        </div>
    </div>
</section>
@endsection

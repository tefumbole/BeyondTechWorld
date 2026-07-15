@php
    $type = $contract->contract_type ?? 'equipment';
    if ($type === 'accommodation') {
        $agreementTitle = 'Student Accommodation Agreement';
    } elseif ($type === 'software_license') {
        $agreementTitle = 'Software License Subscription Agreement';
    } else {
        $agreementTitle = 'Equipment Rental Agreement';
    }
@endphp
<div class="contract-document">
    @if(!empty($header) && ($general_setting->invoice_format ?? '') === 'beyond_a4')
        <div class="contract-letterhead mb-3">
            <img src="{{ $header }}" alt="Letterhead" style="width:100%;max-height:140px;object-fit:contain;">
        </div>
    @else
        <div class="contract-letterhead text-center mb-3">
            @if(!empty($general_setting->site_logo))
                <img src="{{ url('public/logo', $general_setting->site_logo) }}" alt="Logo" style="height:56px;margin-bottom:8px;">
            @endif
            <h2 style="color:#0b3f90;margin:0;">{{ optional($booking->biller)->company_name ?? ($general_setting->site_title ?? 'Beyond Enterprise') }}</h2>
            <p class="text-muted mb-0">{{ optional($booking->biller)->address }} · {{ optional($booking->biller)->phone_number }}</p>
        </div>
    @endif

    <div class="contract-meta mb-4">
        <h4 style="color:#0b3f90;">{{ $agreementTitle }}</h4>
        <p><strong>Booking Ref:</strong> {{ $booking->reference_no }}</p>
        <p><strong>Client:</strong> {{ optional($booking->customer)->name }}</p>
        @if($contract->signed_at)
            <p><strong>Client Signed:</strong> {{ $contract->signed_at->format('d M Y, H:i') }}</p>
        @endif
        @if($contract->admin_signed_at)
            <p><strong>Admin Signed:</strong> {{ $contract->admin_signed_at->format('d M Y, H:i') }} ({{ optional($contract->adminSigner)->name }})</p>
        @endif
    </div>

    @if($type === 'software_license')
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">1. Subscription Summary</h5>
            <p>The client has subscribed for the product(s) / service(s) below. The subscription runs from the start date through the expiry date shown for each item.</p>
        </div>
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">2. Access &amp; Credentials</h5>
            <p>After approval, the client receives portal access to view this subscription and related documents. Credentials must remain confidential.</p>
        </div>
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">3. Fair Use &amp; License Scope</h5>
            <p>Use is limited to the registered client and agreed quantity. Unauthorized sharing or resale may result in suspension without refund.</p>
        </div>
        <div class="contract-section mb-4">
            <h5 style="color:#0b3f90;">4. Product List &amp; Subscription Period</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Product / Service</th>
                            <th>Code</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                            <th>From</th>
                            <th>To (Expires)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['code'] }}</td>
                                <td>{{ $item['qty'] }}</td>
                                <td>{{ number_format($item['unit_price'], 2) }}</td>
                                <td>{{ number_format($item['total'], 2) }}</td>
                                <td>{{ $item['start'] ? date('d M Y', strtotime($item['start'])) : 'As agreed' }}</td>
                                <td>{{ $item['end'] ? date('d M Y', strtotime($item['end'])) : 'As agreed' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p><strong>Grand Total: {{ number_format($booking->grand_total, 2) }}</strong></p>
        </div>
    @elseif($type === 'accommodation')
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">1. Room Assignment &amp; Term</h5>
            <p>Student accommodation at our facility for the period shown below.</p>
        </div>
        <div class="contract-section mb-4">
            <h5 style="color:#0b3f90;">2. Room List &amp; Pricing</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Room / Unit</th>
                            <th>Code</th>
                            <th>Qty</th>
                            <th>Monthly Rent</th>
                            <th>Subtotal</th>
                            <th>Until</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['code'] }}</td>
                                <td>{{ $item['qty'] }}</td>
                                <td>{{ number_format($item['unit_price'], 2) }}</td>
                                <td>{{ number_format($item['total'], 2) }}</td>
                                <td>{{ $item['end'] ? date('d M Y', strtotime($item['end'])) : 'As scheduled' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p><strong>Grand Total: {{ number_format($booking->grand_total, 2) }}</strong></p>
        </div>
    @else
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">1. Rental Term &amp; Return Time</h5>
            <p>All rented equipment must be returned by the agreed return date and time shown for each item. Failure to return on time will incur penalties.</p>
        </div>
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">2. Late Return Penalties</h5>
            <p>Late return of any equipment will incur penalties including an additional full-day rental charge per day (or part thereof) for each item kept beyond the agreed return time, plus any applicable administrative fees.</p>
        </div>
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">3. Client Responsibility for Damage</h5>
            <p>Broken, lost, stolen, or damaged equipment is the full responsibility of the client. The client agrees to pay repair or replacement costs at the current market value of the affected equipment.</p>
        </div>
        <div class="contract-section mb-4">
            <h5 style="color:#0b3f90;">4. Equipment List &amp; Pricing</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Equipment</th>
                            <th>Code</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                            <th>Return By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['code'] }}</td>
                                <td>{{ $item['qty'] }}</td>
                                <td>{{ number_format($item['unit_price'], 2) }}</td>
                                <td>{{ number_format($item['total'], 2) }}</td>
                                <td>{{ $item['end'] ? date('d M Y, H:i', strtotime($item['end'])) : 'As scheduled' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p><strong>Grand Total: {{ number_format($booking->grand_total, 2) }}</strong></p>
        </div>
    @endif

    @if(!empty($booking->booking_note))
        <div class="contract-section mb-3">
            <h5 style="color:#0b3f90;">Additional Notes</h5>
            <p>{!! \App\Support\BookingNoteFormatter::forDisplay($booking->booking_note) !!}</p>
        </div>
    @endif

    <div class="contract-section mb-4">
        <h5 style="color:#0b3f90;">Acceptance</h5>
        <p>By signing below, the client confirms they have read this agreement, accept all terms, and authorize identity verification via ID card upload.</p>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Client Signature</strong></p>
                @if(!empty($clientSignatureSrc))
                    <img src="{{ $clientSignatureSrc }}" alt="Client signature" style="max-width:280px;max-height:120px;border:1px solid #c6ab47;padding:4px;">
                @endif
            </div>
            @if(!empty($adminSignatureSrc))
                <div class="col-md-6">
                    <p><strong>Authorized Signatory</strong></p>
                    <img src="{{ $adminSignatureSrc }}" alt="Admin signature" style="max-width:280px;max-height:120px;border:1px solid #0b3f90;padding:4px;">
                </div>
            @endif
        </div>
        @if(!empty($contract->id_card_path))
            <p class="mt-3"><strong>ID Verification:</strong> <a href="{{ route('booking.contract.id-card', $contract->id) }}" target="_blank" rel="noopener">View uploaded ID</a></p>
        @endif
    </div>

    @if(!empty($footer) && ($general_setting->invoice_format ?? '') === 'beyond_a4')
        <div class="contract-footer mt-4">
            <img src="{{ $footer }}" alt="Footer" style="width:100%;max-height:80px;object-fit:contain;">
        </div>
    @else
        <div class="contract-footer text-center text-muted small mt-4 pt-3 border-top">
            {{ $general_setting->site_title ?? '' }} · {{ $general_setting->developed_by ?? '' }}
        </div>
    @endif
</div>

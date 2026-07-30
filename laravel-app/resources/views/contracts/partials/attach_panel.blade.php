{{-- Phase 4: attach contracts from event/quotation/booking/sale screens --}}
@php
    $linkType = $linkType ?? null;
    $linkId = isset($linkId) ? (string) $linkId : null;
    if ((! isset($linkedContracts) || $linkedContracts === null) && $linkType && $linkId) {
        $ids = \App\ContractLink::where('link_type', $linkType)->where('link_id', $linkId)->pluck('contract_id');
        $linkedContracts = \App\BtwContract::whereIn('id', $ids)->orderByDesc('created_at')->get();
    }
    $linkedContracts = $linkedContracts ?? collect();
@endphp
@if($linkType && $linkId)
<div class="card mb-3" style="border:1px solid #d7e6f7;">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap:8px;">
            <h6 class="mb-0" style="font-weight:700;color:#0b3f90;">
                {{ $linkType === 'booking' ? 'Rental Contracts' : 'Enterprise Contracts' }}
            </h6>
            <div class="d-flex" style="gap:6px;">
                <a href="{{ route('contracts.create', ['link_type' => $linkType, 'link_id' => $linkId]) }}" class="btn btn-sm btn-primary">
                    <i class="dripicons-plus"></i> Add Contract
                </a>
                <a href="{{ route('contracts.index', ['link_type' => $linkType, 'link_id' => $linkId, 'attach_mode' => 1]) }}" class="btn btn-sm btn-outline-secondary">
                    {{ $linkType === 'booking' ? 'Select from Contracts' : 'Attach existing' }}
                </a>
            </div>
        </div>

        @if($linkType === 'booking')
            <p class="text-muted small mb-2">Select the matching agreement from Contracts: <strong>Equipment Rental</strong>, <strong>Student Accommodation</strong>, <strong>Software License</strong>, or <strong>Studio Rental</strong> — wording matches the existing Rental module exactly.</p>
        @elseif($linkType === 'shareholder')
            <p class="text-muted small mb-2">Uses the <strong>Shareholder Agreement</strong> template (exact Shareholders portal wording).</p>
        @endif

        @if($linkedContracts->count())
            <ul class="list-unstyled mb-0">
                @foreach($linkedContracts as $lc)
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>
                            <strong>{{ $lc->number ?? $lc->id }}</strong>
                            — {{ $lc->title ?? 'Contract' }}
                            <span class="badge badge-light">{{ method_exists($lc, 'statusLabel') ? $lc->statusLabel() : ($lc->status ?? '') }}</span>
                        </span>
                        <a href="{{ route('contracts.show', $lc->id) }}" class="btn btn-xs btn-light">View</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted mb-0 small">No enterprise contracts linked to this record yet.</p>
        @endif
    </div>
</div>
@endif

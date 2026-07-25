@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="ct-title">Contracts</h1>
                <p class="ct-subtitle">Manage contract instances, signatures, and linked records.</p>
            </div>
            <a href="{{ route('contracts.create') }}" class="ct-btn"><i class="dripicons-plus"></i> Create Contract</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="GET" class="ct-card">
            <div class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="ct-label">Search</label>
                    <input type="search" name="q" value="{{ $q ?? '' }}" class="ct-field" placeholder="Number, title, party…">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="ct-label">Type</label>
                    <select name="type_id" class="ct-field">
                        <option value="">All types</option>
                        @foreach($types ?? [] as $type)
                            <option value="{{ $type->id }}" @if(($typeId ?? '') == $type->id) selected @endif>{{ $type->name ?? $type->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="ct-label">Status</label>
                    <select name="status" class="ct-field">
                        <option value="">All statuses</option>
                        @foreach([
                            'draft' => 'Draft',
                            'in_review' => 'In Review',
                            'ready_to_send' => 'Ready to Send',
                            'awaiting_client_signature' => 'Awaiting Client',
                            'awaiting_admin_signature' => 'Awaiting Admin',
                            'signed' => 'Signed',
                            'declined' => 'Declined',
                            'expired' => 'Expired',
                            'cancelled' => 'Cancelled',
                            'superseded' => 'Superseded',
                        ] as $val => $label)
                            <option value="{{ $val }}" @if(($status ?? '') === $val) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="ct-btn" style="width:100%;justify-content:center;">Filter</button>
                </div>
            </div>
        </form>

        <div class="ct-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Party A</th>
                            <th>Party B</th>
                            <th>Linked</th>
                            <th>Signature</th>
                            <th>Effective</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts ?? [] as $contract)
                            @php
                                $statusClass = 'ct-badge';
                                if ($contract->status === 'signed') $statusClass .= ' ct-badge-success';
                                elseif (in_array($contract->status, ['awaiting_client_signature', 'awaiting_admin_signature', 'ready_to_send'], true)) $statusClass .= ' ct-badge-warning';
                                elseif (in_array($contract->status, ['declined', 'cancelled', 'expired'], true)) $statusClass .= ' ct-badge-danger';
                            @endphp
                            <tr>
                                <td><strong>{{ $contract->number }}</strong></td>
                                <td>{{ $contract->title }}</td>
                                <td>{{ optional($contract->type)->name ?? optional($contract->type)->code ?? '—' }}</td>
                                <td><span class="{{ $statusClass }}">{{ $contract->statusLabel() }}</span></td>
                                <td>{{ optional($contract->partyA)->displayName() ?? '—' }}</td>
                                <td>{{ optional($contract->partyB)->displayName() ?? '—' }}</td>
                                <td>
                                    @if($contract->primary_link_type && $contract->primary_link_id)
                                        <span class="ct-badge ct-badge-info">{{ ucfirst($contract->primary_link_type) }} #{{ $contract->primary_link_id }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ method_exists($contract, 'signatureProgress') ? $contract->signatureProgress() : '—' }}</td>
                                <td>{{ $contract->effective_date ? \Carbon\Carbon::parse($contract->effective_date)->format('M j, Y') : '—' }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('contracts.show', $contract->id) }}" class="btn btn-sm btn-primary">View</a>
                                    @if(! empty($attachMode) && ! empty($linkType) && ! empty($linkId))
                                        <form method="POST" action="{{ route('contracts.attach', $contract->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="link_type" value="{{ $linkType }}">
                                            <input type="hidden" name="link_id" value="{{ $linkId }}">
                                            <input type="hidden" name="primary" value="1">
                                            <button type="submit" class="btn btn-sm btn-success">Attach</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No contracts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(isset($contracts) && method_exists($contracts, 'links'))
                <div class="mt-3">{{ $contracts->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection

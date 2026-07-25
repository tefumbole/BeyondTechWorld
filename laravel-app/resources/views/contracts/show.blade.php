@extends('layout.main')

@section('content')
@php
    use App\BtwContract;
    $editable = method_exists($contract, 'isEditable') ? $contract->isEditable() : in_array($contract->status, ['draft', 'in_review'], true);
    $signed = method_exists($contract, 'isSigned') ? $contract->isSigned() : ($contract->status === 'signed');
    $awaitingAdmin = $contract->status === BtwContract::STATUS_AWAITING_ADMIN;
    $canSend = in_array($contract->status, [BtwContract::STATUS_DRAFT, BtwContract::STATUS_IN_REVIEW, BtwContract::STATUS_READY_TO_SEND], true);
    $partyA = optional($contract->partyA)->snapshot() ?? [];
    $partyB = optional($contract->partyB)->snapshot() ?? [];
    $auditRows = $contract->auditLogs ?? collect();
    $documents = $contract->documents ?? collect();
@endphp
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h1 class="ct-title">{{ $contract->title }}</h1>
                <p class="ct-subtitle mb-1">
                    <strong>{{ $contract->number }}</strong>
                    · {{ optional($contract->type)->name ?? 'Contract' }}
                    · <span class="ct-badge">{{ $contract->statusLabel() }}</span>
                </p>
            </div>
            <div class="d-flex flex-wrap" style="gap:8px;">
                @if($editable)
                    <a href="{{ route('contracts.edit', $contract->id) }}" class="ct-btn-secondary"><i class="dripicons-pencil"></i> Edit</a>
                @endif
                <a href="{{ route('contracts.preview', $contract->id) }}" target="_blank" class="ct-btn-secondary"><i class="dripicons-preview"></i> Preview PDF</a>
                @if($canSend)
                    <form method="POST" action="{{ route('contracts.ready', $contract->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="ct-btn-secondary">Ready to Send</button>
                    </form>
                    <form method="POST" action="{{ route('contracts.send', $contract->id) }}" class="d-inline" onsubmit="return confirm('Send this contract for signature?');">
                        @csrf
                        <button type="submit" class="ct-btn">Send for Signature</button>
                    </form>
                @endif
                @if($awaitingAdmin)
                    <form method="POST" action="{{ route('contracts.sign_admin', $contract->id) }}" class="d-inline" onsubmit="return confirm('Apply admin signature?');">
                        @csrf
                        <button type="submit" class="ct-btn">Admin Sign</button>
                    </form>
                @endif
                @if($signed)
                    <button type="button" class="ct-btn-secondary" data-toggle="modal" data-target="#ct-amend-modal">
                        <i class="dripicons-document-edit"></i> Create Amendment
                    </button>
                @endif
                @if($contract->supersedes)
                    <a href="{{ route('contracts.show', $contract->supersedes) }}" class="ct-btn-secondary">View superseded original</a>
                @endif
                @if($contract->superseded_by)
                    <a href="{{ route('contracts.show', $contract->superseded_by) }}" class="ct-btn-secondary">View amendment</a>
                @endif
                @if(! in_array($contract->status, [BtwContract::STATUS_CANCELLED, BtwContract::STATUS_SUPERSEDED, BtwContract::STATUS_SIGNED], true))
                    <form method="POST" action="{{ route('contracts.cancel', $contract->id) }}" class="d-inline" onsubmit="return confirm('Cancel this contract?');">
                        @csrf
                        <button type="submit" class="ct-btn-danger">Cancel</button>
                    </form>
                @endif
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(! empty($missing))
            <div class="alert alert-warning">
                <strong>Missing before send:</strong> {{ implode(', ', $missing) }}
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="ct-card">
                    <h5 class="mb-3">Contract Details</h5>
                    <div class="row">
                        <div class="col-md-6 mb-2"><strong>Effective date:</strong> {{ $contract->effective_date ? \Carbon\Carbon::parse($contract->effective_date)->format('M j, Y') : '—' }}</div>
                        <div class="col-md-6 mb-2"><strong>Period:</strong>
                            {{ $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->format('M j, Y') : '—' }}
                            – {{ $contract->end_date ? \Carbon\Carbon::parse($contract->end_date)->format('M j, Y') : '—' }}
                        </div>
                        <div class="col-md-6 mb-2"><strong>Value:</strong> {{ $contract->value ? number_format((float) $contract->value, 2).' '.($contract->currency ?? '') : '—' }}</div>
                        <div class="col-md-6 mb-2"><strong>Jurisdiction:</strong> {{ $contract->jurisdiction ?: '—' }}</div>
                        <div class="col-md-12 mb-2"><strong>Purpose:</strong> {{ $contract->purpose ?: '—' }}</div>
                        <div class="col-md-12 mb-2"><strong>Payment schedule:</strong> {{ $contract->payment_schedule ?: '—' }}</div>
                        <div class="col-md-6 mb-2"><strong>Template:</strong> {{ optional($contract->template)->name ?? '—' }}</div>
                        <div class="col-md-6 mb-2"><strong>Signature progress:</strong> {{ $contract->signatureProgress() }}</div>
                    </div>
                </div>

                <div class="ct-card">
                    <h5 class="mb-3">Parties</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ optional($contract->partyA)->role_label ?? 'Party A' }}</h6>
                            <p class="mb-1">{{ $partyA['name'] ?? '—' }}</p>
                            <p class="mb-1 text-muted small">{{ $partyA['email'] ?? '' }} {{ $partyA['phone'] ?? '' }}</p>
                            <p class="mb-0 text-muted small">{{ $partyA['address'] ?? '' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ optional($contract->partyB)->role_label ?? 'Party B' }}</h6>
                            <p class="mb-1">{{ $partyB['name'] ?? '—' }}</p>
                            <p class="mb-1 text-muted small">{{ $partyB['email'] ?? '' }} {{ $partyB['phone'] ?? '' }}</p>
                            <p class="mb-0 text-muted small">{{ $partyB['address'] ?? '' }}</p>
                        </div>
                    </div>
                </div>

                <div class="ct-card">
                    <h5 class="mb-3">Signatories</h5>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Stage</th>
                                    <th>Status</th>
                                    <th>Signed</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($contract->signatories ?? [] as $sig)
                                    <tr>
                                        <td>{{ ucwords(str_replace('_', ' ', $sig->role)) }}</td>
                                        <td>{{ $sig->display_name }}</td>
                                        <td>{{ $sig->email ?: '—' }}</td>
                                        <td>{{ $sig->stage }}</td>
                                        <td>
                                            @php
                                                $sigBadge = 'ct-badge';
                                                if ($sig->status === 'signed') $sigBadge .= ' ct-badge-success';
                                                elseif ($sig->status === 'declined') $sigBadge .= ' ct-badge-danger';
                                                elseif ($sig->status === 'pending') $sigBadge .= ' ct-badge-warning';
                                            @endphp
                                            <span class="{{ $sigBadge }}">{{ ucfirst($sig->status) }}</span>
                                        </td>
                                        <td>{{ $sig->signed_at ? \Carbon\Carbon::parse($sig->signed_at)->format('M j, Y H:i') : '—' }}</td>
                                        <td class="text-right">
                                            @if($sig->status === 'pending' && ! in_array($contract->status, [BtwContract::STATUS_DRAFT, BtwContract::STATUS_CANCELLED], true))
                                                <form method="POST" action="{{ route('contracts.resend', [$contract->id, $sig->id]) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Resend</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-muted">No signatories configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="ct-card">
                    <h5 class="mb-3">Reminders</h5>
                    <p class="text-muted small">Schedule specific date/times for this contract (Task Manager style). Not system-wide.</p>
                    <form method="POST" action="{{ route('contracts.reminders.store', $contract->id) }}" class="mb-3">
                        @csrf
                        <div class="mb-2">
                            <label class="ct-label">When</label>
                            <input type="datetime-local" name="reminder_time" class="ct-field" required>
                        </div>
                        <div class="mb-2">
                            <label class="ct-label">Label (optional)</label>
                            <input type="text" name="label" class="ct-field" placeholder="e.g. Follow up with client">
                        </div>
                        <div class="mb-2">
                            <label class="ct-label">Message (optional)</label>
                            <textarea name="message" class="ct-field" rows="2" placeholder="Custom WhatsApp/email text"></textarea>
                        </div>
                        <button type="submit" class="ct-btn btn-sm"><i class="dripicons-plus"></i> Add reminder</button>
                    </form>
                    <ul class="list-unstyled mb-0">
                        @forelse($contract->reminders ?? [] as $rem)
                            <li class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                <div>
                                    <strong>{{ \Carbon\Carbon::parse($rem->reminder_time)->format('M j, Y H:i') }}</strong>
                                    @if($rem->label)<div class="small">{{ $rem->label }}</div>@endif
                                    <div class="small text-muted">{{ $rem->is_sent ? 'Sent '.optional($rem->sent_at)->format('M j H:i') : 'Scheduled' }}</div>
                                </div>
                                @if(! $rem->is_sent)
                                    <form method="POST" action="{{ route('contracts.reminders.destroy', [$contract->id, $rem->id]) }}" onsubmit="return confirm('Remove this reminder?');">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-danger">×</button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="text-muted small">No reminders yet.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="ct-card">
                    <h5 class="mb-3">Links</h5>
                    @forelse($contract->links ?? [] as $link)
                        <div class="mb-2">
                            <span class="ct-badge {{ $link->is_primary ? 'ct-badge-info' : '' }}">
                                {{ ucfirst($link->link_type) }} #{{ $link->link_id }}
                                @if($link->is_primary) (primary) @endif
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No linked records.</p>
                    @endforelse
                </div>

                <div class="ct-card">
                    <h5 class="mb-3">Final Documents</h5>
                    @forelse($documents as $doc)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>{{ ucfirst($doc->kind ?? 'document') }} (rev {{ optional($doc->revision)->revision_no ?? '—' }})</span>
                            <a href="{{ route('contracts.download', [$contract->id, $doc->id]) }}" class="btn btn-sm btn-outline-primary">Download</a>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No generated documents yet.</p>
                    @endforelse
                </div>

                <div class="ct-card">
                    <h5 class="mb-3">Recent Activity</h5>
                    @forelse($auditRows->take(8) as $log)
                        <div class="mb-2 pb-2 border-bottom">
                            <div class="small text-muted">{{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M j, Y H:i') : '' }}</div>
                            <div><strong>{{ ucwords(str_replace('_', ' ', $log->action)) }}</strong></div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No audit entries yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if($signed)
    <div class="modal fade" id="ct-amend-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('contracts.supersede', $contract->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Amendment</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Signed contracts are immutable. This creates a <strong>new draft</strong> copied from the same template
                        and parties, marks this contract as <em>superseded</em>, and lets you edit the amendment before sending.
                    </p>
                    <label class="ct-label">Amendment reason (optional)</label>
                    <textarea name="amendment_reason" class="ct-field" rows="3" placeholder="e.g. Extend end date; revise payment schedule"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="ct-btn">Create amendment draft</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</section>
@endsection

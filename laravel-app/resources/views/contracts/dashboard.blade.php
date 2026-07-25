@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h1 class="ct-title">Contracts Dashboard</h1>
                <p class="ct-subtitle">Pipeline, expiry alerts, and recent activity.</p>
            </div>
            <div class="d-flex" style="gap:8px;">
                <a href="{{ route('contracts.report') }}" class="ct-btn-secondary"><i class="dripicons-document"></i> Reports</a>
                <a href="{{ route('contracts.create') }}" class="ct-btn"><i class="dripicons-plus"></i> New Contract</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="ct-card" style="border-left:4px solid #f59e0b;">
                    <div class="text-muted small">Awaiting client</div>
                    <div style="font-size:2rem;font-weight:800;color:#0b3f90;">{{ $awaitingClient }}</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ct-card" style="border-left:4px solid #7b61ff;">
                    <div class="text-muted small">Awaiting admin</div>
                    <div style="font-size:2rem;font-weight:800;color:#0b3f90;">{{ $awaitingAdmin }}</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ct-card" style="border-left:4px solid #10b981;">
                    <div class="text-muted small">Signed (all)</div>
                    <div style="font-size:2rem;font-weight:800;color:#0b3f90;">{{ $signed }}</div>
                    <div class="text-muted small">{{ $signedThisMonth }} this month</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="ct-card" style="border-left:4px solid #0b3f90;">
                    <div class="text-muted small">Draft / in review</div>
                    <div style="font-size:2rem;font-weight:800;color:#0b3f90;">{{ $draft }}</div>
                    <div class="text-muted small">{{ $total }} total contracts</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="ct-card">
                    <h5 class="mb-3" style="color:#0b3f90;">By type</h5>
                    @if(($byType ?? collect())->count())
                        <ul class="list-unstyled mb-0">
                            @foreach($byType as $name => $count)
                                <li class="d-flex justify-content-between py-1 border-bottom">
                                    <span>{{ $name }}</span><strong>{{ $count }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No contracts yet.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="ct-card">
                    <h5 class="mb-3" style="color:#0b3f90;">Expiring within {{ $expiryDays }} days</h5>
                    @if(($expiring ?? collect())->count())
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Number</th><th>Party B</th><th>Ends</th></tr></thead>
                                <tbody>
                                    @foreach($expiring as $c)
                                        <tr>
                                            <td><a href="{{ route('contracts.show', $c->id) }}">{{ $c->number }}</a></td>
                                            <td>{{ optional($c->partyB)->snapshot()['name'] ?? '—' }}</td>
                                            <td>{{ $c->end_date ? \Carbon\Carbon::parse($c->end_date)->format('M j, Y') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">None in the alert window.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-3">
                <div class="ct-card">
                    <h5 class="mb-3" style="color:#0b3f90;">Stale awaiting signature (3+ days)</h5>
                    @if(($staleAwaiting ?? collect())->count())
                        <ul class="list-unstyled mb-0">
                            @foreach($staleAwaiting as $c)
                                <li class="py-2 border-bottom">
                                    <a href="{{ route('contracts.show', $c->id) }}"><strong>{{ $c->number }}</strong></a>
                                    — {{ $c->statusLabel() }}
                                    <span class="text-muted small">· {{ optional($c->partyB)->snapshot()['name'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No stale signatures.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="ct-card">
                    <h5 class="mb-3" style="color:#0b3f90;">Recently signed</h5>
                    @if(($recentSigned ?? collect())->count())
                        <ul class="list-unstyled mb-0">
                            @foreach($recentSigned as $c)
                                <li class="py-2 border-bottom">
                                    <a href="{{ route('contracts.show', $c->id) }}"><strong>{{ $c->number }}</strong></a>
                                    — {{ $c->title }}
                                    <span class="text-muted small">· {{ $c->signed_at ? \Carbon\Carbon::parse($c->signed_at)->format('M j, Y') : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No signed contracts yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid" style="max-width:1100px;margin:0 auto;">
        <div class="mb-3">
            <h3 style="color:#0b3f90;font-weight:800;">Pa Ngwayu funeral pledges</h3>
            <p class="text-muted mb-1">Admin: Pa Ngwayu Richard · family budget
                <a href="{{ $publicUrl }}" target="_blank">{{ $publicUrl }}</a>
                · church program &amp; eulogies
                <a href="{{ $rememberUrl }}" target="_blank">{{ $rememberUrl }}</a>
            </p>
            @if($data)
                <p class="mb-0"><strong>{{ number_format($data['raised']) }}</strong> of
                    <strong>{{ number_format($data['target']) }}</strong> XAF
                    ({{ $data['percent'] }}%)</p>
            @endif
        </div>

        @if($data)
        <div class="card card-body mb-3">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Target</th>
                            <th>Raised</th>
                            <th>Left</th>
                            <th>Names</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['items'] as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['target'] !== null ? number_format($item['target']) : 'Open' }}</td>
                                <td>{{ number_format($item['committed']) }}</td>
                                <td>{{ $item['remaining'] !== null ? number_format($item['remaining']) : '—' }}</td>
                                <td>{{ implode(', ', $item['names']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="card card-body">
            <h5>Recent pledges</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Item</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pledges as $p)
                            <tr>
                                <td>{{ $p->created_at }}</td>
                                <td>{{ $p->name }}</td>
                                <td>{{ $p->phone }}</td>
                                <td>{{ optional($p->item)->name }}</td>
                                <td>{{ number_format($p->amount) }}</td>
                                <td>{{ $p->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No pledges yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-body mt-3">
            <h5>Eulogies</h5>
            @forelse($eulogies as $e)
                <div class="mb-3 pb-3" style="border-bottom:1px solid #eee;">
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        @if($e->selfie_path)
                            <img src="{{ asset($e->selfie_path) }}" alt="{{ $e->name }}" style="height:56px;width:56px;border-radius:50%;object-fit:cover;">
                        @endif
                        <div>
                    <strong>{{ $e->name }}</strong>
                    <span class="text-muted small"> · {{ $e->phone }} · {{ $e->created_at }}</span>
                    <p class="mb-1">{{ $e->body }}</p>
                    @if($e->signature_path)
                        <img src="{{ asset($e->signature_path) }}" alt="Signature" style="height:48px;background:#fff;">
                    @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No eulogies yet.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection

@extends('layout.main')
@section('content')
<style>
    .mdq-shell { max-width: 1100px; }
    .mdq-title { font-size: 1.4rem; font-weight: 700; margin: 0; }
    .mdq-meta { color: #64748b; font-size: .92rem; }
    .mdq-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.15rem; margin-bottom: 1rem; }
    .mdq-bar { height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
    .mdq-bar > span { display: block; height: 100%; background: #0f766e; transition: width .35s ease; }
    .mdq-bar.is-partial > span { background: #d97706; }
    .mdq-bar.is-failed > span { background: #dc2626; }
    .mdq-bar.is-completed > span { background: #16a34a; }
    .mdq-badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
    .mdq-badge.queued { background: #e0f2fe; color: #0369a1; }
    .mdq-badge.sending { background: #fef3c7; color: #b45309; }
    .mdq-badge.completed { background: #dcfce7; color: #15803d; }
    .mdq-badge.partial { background: #ffedd5; color: #c2410c; }
    .mdq-badge.failed { background: #fee2e2; color: #b91c1c; }
    .mdq-live { display: inline-flex; align-items: center; gap: 6px; color: #0f766e; font-size: .85rem; font-weight: 600; }
    .mdq-live .dot { width: 8px; height: 8px; border-radius: 50%; background: #14b8a6; animation: mdq-pulse 1.2s infinite; }
    @keyframes mdq-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
</style>

@php $module = $module ?? 'all'; @endphp
<section class="forms">
    <div class="container-fluid mdq-shell">
        @if($module === 'invitations')
            @php $oiTab = 'queued'; @endphp
            @include('online_invitation.partials.tabs')
        @endif
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
            <div>
                <h1 class="mdq-title">Queued Messages</h1>
                <p class="mdq-meta mb-0">
                    @if($module === 'invitations')
                        Live WhatsApp digital invitation delivery progress.
                    @elseif($module === 'letters')
                        Live WhatsApp letter delivery progress.
                    @else
                        Live WhatsApp delivery progress (letters &amp; invitations).
                    @endif
                </p>
            </div>
            <div class="d-flex align-items-center" style="gap:12px;">
                <span class="mdq-live" id="mdq-live" style="{{ ($activeCount ?? 0) > 0 ? '' : 'display:none' }}">
                    <span class="dot"></span> Sending in progress
                </span>
                @if($module === 'invitations')
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('online_invitation.invitations.index') }}">All Invitations</a>
                @else
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('letter.index.sent') }}">Sent Letters</a>
                @endif
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(!empty($tablesMissing))
            <div class="alert alert-warning">
                Delivery queue tables are not installed yet. Run migrations, then send a letter again.
            </div>
        @endif

        <div class="mdq-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="mdq-table">
                    <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Sent</th>
                        <th>Failed</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($batches as $batch)
                        <tr data-batch-id="{{ $batch->id }}">
                            <td>
                                <strong>{{ $batch->title ?: ('Batch #'.$batch->id) }}</strong>
                                @if(($batch->type ?? '') === 'online_invitation')
                                    <div class="mdq-meta">Digital Invitations</div>
                                @elseif($batch->letter_id)
                                    <div class="mdq-meta">Letter #{{ $batch->letter_id }}</div>
                                @endif
                            </td>
                            <td><span class="mdq-badge {{ $batch->status }}" data-field="status">{{ $batch->status }}</span></td>
                            <td style="min-width:160px;">
                                <div class="mdq-bar {{ $batch->status }}" data-field="bar"><span style="width: {{ $batch->progressPercent() }}%"></span></div>
                                <div class="mdq-meta mt-1" data-field="progress-label">{{ $batch->progressPercent() }}%</div>
                            </td>
                            <td data-field="sent">{{ $batch->sent_count }} / {{ $batch->total }}</td>
                            <td data-field="failed">{{ $batch->failed_count }}</td>
                            <td data-field="updated">{{ optional($batch->updated_at)->format('d M H:i:s') }}</td>
                            <td><a class="btn btn-sm btn-info" href="{{ route('message.delivery.show', $batch->id) }}">Details</a></td>
                        </tr>
                    @empty
                        <tr id="mdq-empty"><td colspan="7">
                            @if(($module ?? '') === 'invitations')
                                No invitation deliveries yet. Send selected invitations to see progress here.
                            @else
                                No queued deliveries yet. Send a letter or invitation to see progress here.
                            @endif
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($batches, 'links'))
                <div class="mt-3">{{ $batches->links() }}</div>
            @endif
        </div>
    </div>
</section>

<script>
(function () {
    var pollUrl = @json(route('message.delivery.status', ['module' => $module ?? 'all']));
    var live = document.getElementById('mdq-live');
    var table = document.getElementById('mdq-table');
    if (!table) return;

    function applyBatch(b) {
        var row = table.querySelector('tr[data-batch-id="' + b.id + '"]');
        if (!row) return;
        var statusEl = row.querySelector('[data-field="status"]');
        var bar = row.querySelector('[data-field="bar"]');
        var label = row.querySelector('[data-field="progress-label"]');
        var sent = row.querySelector('[data-field="sent"]');
        var failed = row.querySelector('[data-field="failed"]');
        var updated = row.querySelector('[data-field="updated"]');
        if (statusEl) {
            statusEl.className = 'mdq-badge ' + b.status;
            statusEl.textContent = b.status;
        }
        if (bar) {
            bar.className = 'mdq-bar ' + b.status;
            var span = bar.querySelector('span');
            if (span) span.style.width = b.progress + '%';
        }
        if (label) label.textContent = b.progress + '%';
        if (sent) sent.textContent = b.sent_count + ' / ' + b.total;
        if (failed) failed.textContent = b.failed_count;
        if (updated && b.updated_at) updated.textContent = b.updated_at;
    }

    function tick() {
        var ids = Array.prototype.map.call(table.querySelectorAll('tr[data-batch-id]'), function (tr) {
            return tr.getAttribute('data-batch-id');
        });
        var qs = ids.length ? ('?ids[]=' + ids.join('&ids[]=')) : '';
        fetch(pollUrl + qs, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                (data.batches || []).forEach(applyBatch);
                if (live) live.style.display = (data.active > 0) ? '' : 'none';
                var stillActive = (data.active > 0) || (data.batches || []).some(function (b) { return b.active; });
                if (stillActive) setTimeout(tick, 2500);
                else setTimeout(tick, 8000);
            })
            .catch(function () { setTimeout(tick, 8000); });
    }
    setTimeout(tick, 1500);
})();
</script>
@endsection

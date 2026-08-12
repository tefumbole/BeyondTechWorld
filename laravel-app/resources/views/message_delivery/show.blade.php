@extends('layout.main')
@section('content')
<style>
    .mdq-shell { max-width: 1000px; }
    .mdq-title { font-size: 1.35rem; font-weight: 700; margin: 0; }
    .mdq-meta { color: #64748b; font-size: .92rem; }
    .mdq-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.15rem; margin-bottom: 1rem; }
    .mdq-bar { height: 12px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin: .75rem 0; }
    .mdq-bar > span { display: block; height: 100%; background: #0f766e; transition: width .35s ease; }
    .mdq-badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
    .mdq-badge.queued { background: #e0f2fe; color: #0369a1; }
    .mdq-badge.sending { background: #fef3c7; color: #b45309; }
    .mdq-badge.sent, .mdq-badge.completed { background: #dcfce7; color: #15803d; }
    .mdq-badge.partial { background: #ffedd5; color: #c2410c; }
    .mdq-badge.failed, .mdq-badge.skipped { background: #fee2e2; color: #b91c1c; }
</style>

@php $module = $module ?? ((($batch->type ?? '') === 'online_invitation') ? 'invitations' : 'all'); @endphp
<section class="forms">
    <div class="container-fluid mdq-shell">
        @if($module === 'invitations')
            @php $oiTab = 'queued'; @endphp
            @include('online_invitation.partials.tabs')
        @endif
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
            <div>
                <h1 class="mdq-title">{{ $batch->title ?: ('Batch #'.$batch->id) }}</h1>
                <p class="mdq-meta mb-0">
                    Batch #{{ $batch->id }}
                    @if(($batch->type ?? '') === 'online_invitation') · Digital Invitations
                    @elseif($batch->letter_id) · Letter #{{ $batch->letter_id }}
                    @endif
                    @if(optional($batch->queuedBy)->name) · Queued by {{ $batch->queuedBy->name }} @endif
                </p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('message.delivery.index', $module === 'invitations' ? ['module' => 'invitations'] : []) }}">&larr; All queues</a>
        </div>

        <div class="mdq-card" id="mdq-summary"
             data-status-url="{{ route('message.delivery.item-status', $batch->id) }}"
             data-active="{{ $batch->isActive() ? '1' : '0' }}">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                <span class="mdq-badge {{ $batch->status }}" data-field="status">{{ $batch->status }}</span>
                <span class="mdq-meta" data-field="counts">{{ $batch->sent_count }} sent · {{ $batch->failed_count }} failed · {{ $batch->total }} total</span>
            </div>
            <div class="mdq-bar"><span data-field="bar" style="width: {{ $batch->progressPercent() }}%"></span></div>
            <div class="mdq-meta" data-field="progress">{{ $batch->progressPercent() }}% complete</div>
        </div>

        <div class="mdq-card">
            <h2 class="h5 mb-3">Recipients</h2>
            <div class="table-responsive">
                <table class="table mb-0" id="mdq-items">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($batch->items as $item)
                        <tr data-item-id="{{ $item->id }}">
                            <td>{{ $item->recipient_name ?: '—' }}</td>
                            <td>{{ $item->phone ?: '—' }}</td>
                            <td>{{ strtoupper($item->role) }}</td>
                            <td><span class="mdq-badge {{ $item->status }}" data-field="item-status">{{ $item->status }}</span></td>
                            <td class="text-danger small" data-field="item-error">{{ $item->error }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var summary = document.getElementById('mdq-summary');
    if (!summary) return;
    var url = summary.getAttribute('data-status-url');
    var active = summary.getAttribute('data-active') === '1';

    function tick() {
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var statusEl = summary.querySelector('[data-field="status"]');
                var counts = summary.querySelector('[data-field="counts"]');
                var bar = summary.querySelector('[data-field="bar"]');
                var progress = summary.querySelector('[data-field="progress"]');
                if (statusEl) {
                    statusEl.className = 'mdq-badge ' + data.status;
                    statusEl.textContent = data.status;
                }
                if (counts) counts.textContent = data.sent_count + ' sent · ' + data.failed_count + ' failed · ' + data.total + ' total';
                if (bar) bar.style.width = data.progress + '%';
                if (progress) progress.textContent = data.progress + '% complete';

                (data.items || []).forEach(function (item) {
                    var row = document.querySelector('tr[data-item-id="' + item.id + '"]');
                    if (!row) return;
                    var st = row.querySelector('[data-field="item-status"]');
                    var err = row.querySelector('[data-field="item-error"]');
                    if (st) {
                        st.className = 'mdq-badge ' + item.status;
                        st.textContent = item.status;
                    }
                    if (err) err.textContent = item.error || '';
                });

                if (data.active) setTimeout(tick, 2000);
            })
            .catch(function () {
                if (active) setTimeout(tick, 4000);
            });
    }
    if (active) setTimeout(tick, 1200);
})();
</script>
@endsection

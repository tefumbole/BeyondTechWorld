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
            <div class="d-flex align-items-center flex-wrap" style="gap:12px;">
                <span class="mdq-live" id="mdq-live" style="{{ ($activeCount ?? 0) > 0 ? '' : 'display:none' }}">
                    <span class="dot"></span> Sending in progress
                </span>
                <button type="button" id="mdq-bulk-delete-btn" class="btn btn-danger btn-sm" disabled>Delete selected</button>
                @if($module === 'invitations')
                    <a class="btn btn-outline-danger btn-sm" href="{{ route('online_invitation.invitations.index', ['status' => 'failed']) }}">Failed Invitations</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('online_invitation.invitations.index') }}">All Invitations</a>
                @else
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('online_invitation.queued') }}">Invitation queue</a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('letter.index.sent') }}">Sent Letters</a>
                @endif
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if($module === 'invitations')
            <p class="mdq-meta mb-3">Failed batches stay listed here for history. Use <strong>Resend failed</strong> below, or open <strong>Failed Invitations</strong> to Send Again on each card.</p>
        @endif
        @if(!empty($tablesMissing))
            <div class="alert alert-warning">
                Delivery queue tables are not installed yet. Run migrations, then send a letter again.
            </div>
        @endif

        <form id="mdq-bulk-delete-form" action="{{ route('message.delivery.bulk_destroy') }}" method="POST" class="d-none">
            @csrf
            @if($module === 'invitations')
                <input type="hidden" name="module" value="invitations">
            @elseif($module === 'letters')
                <input type="hidden" name="module" value="letters">
            @endif
            <div id="mdq-bulk-delete-inputs"></div>
        </form>

        <div class="mdq-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="mdq-table">
                    <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" id="mdq-select-all" title="Select all">
                        </th>
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
                                <input type="checkbox" class="mdq-batch-checkbox" value="{{ $batch->id }}">
                            </td>
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
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-info" href="{{ route('message.delivery.show', $batch->id) }}">Details</a>
                                @if(($batch->type ?? '') === 'online_invitation' && (int) $batch->failed_count > 0)
                                    <form action="{{ route('message.delivery.resend', $batch->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Resend all failed invitations in this batch?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">Resend failed</button>
                                    </form>
                                @endif
                                <form action="{{ route('message.delivery.destroy', $batch->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this queued message batch?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr id="mdq-empty"><td colspan="8">
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

<script type="text/javascript">
(function () {
    function activateModuleTabs() {
        var module = @json($module ?? 'all');
        $('#side-main-menu ul.collapse li.active').removeClass('active');
        $('#side-main-menu > li > a').removeClass('menu-parent-active').attr('aria-expanded', 'false');
        $('#side-main-menu ul.collapse').removeClass('show');

        if (module === 'invitations') {
            $("ul#online_invitation").siblings('a').attr('aria-expanded', 'true').addClass('menu-parent-active');
            $("ul#online_invitation").addClass('show');
            $("ul#online_invitation #online-invitation-queued-menu").addClass('active');
        } else {
            $("ul#letter").siblings('a').attr('aria-expanded', 'true').addClass('menu-parent-active');
            $("ul#letter").addClass('show');
            $("ul#letter #letter-queued-menu").addClass('active');
        }

        if (typeof window.beyondBuildModuleTabs === 'function') {
            window.beyondBuildModuleTabs();
        }
    }

    if (window.jQuery) {
        $(activateModuleTabs);
        // Layout also builds tabs on ready — rebuild after so our active item wins.
        $(function () { setTimeout(activateModuleTabs, 0); });
    }
})();
</script>
<script>
(function () {
    var pollUrl = @json(route('message.delivery.status', ['module' => $module ?? 'all']));
    var live = document.getElementById('mdq-live');
    var table = document.getElementById('mdq-table');
    if (!table) return;

    function updateBulkBtn() {
        var btn = document.getElementById('mdq-bulk-delete-btn');
        var boxes = table.querySelectorAll('.mdq-batch-checkbox:checked');
        if (btn) btn.disabled = boxes.length === 0;
        var all = table.querySelectorAll('.mdq-batch-checkbox');
        var selectAll = document.getElementById('mdq-select-all');
        if (selectAll && all.length) {
            selectAll.checked = boxes.length === all.length;
        }
    }

    var selectAll = document.getElementById('mdq-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            table.querySelectorAll('.mdq-batch-checkbox').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkBtn();
        });
    }
    table.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('mdq-batch-checkbox')) {
            updateBulkBtn();
        }
    });

    var bulkBtn = document.getElementById('mdq-bulk-delete-btn');
    if (bulkBtn) {
        bulkBtn.addEventListener('click', function () {
            var ids = Array.prototype.map.call(table.querySelectorAll('.mdq-batch-checkbox:checked'), function (cb) {
                return cb.value;
            });
            if (!ids.length) return;
            if (!confirm('Delete ' + ids.length + ' selected queued message batch(es)? This cannot be undone.')) return;
            var container = document.getElementById('mdq-bulk-delete-inputs');
            var form = document.getElementById('mdq-bulk-delete-form');
            if (!container || !form) return;
            container.innerHTML = '';
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                container.appendChild(input);
            });
            form.submit();
        });
    }

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
        var qs = ids.length ? ((pollUrl.indexOf('?') >= 0 ? '&' : '?') + 'ids[]=' + ids.join('&ids[]=')) : '';
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

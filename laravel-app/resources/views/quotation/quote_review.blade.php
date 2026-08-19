@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        @include('quotation.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h3 class="mb-1">Review client quote</h3>
                <p class="text-muted mb-0">
                    {{ $quotation->reference_no }}
                    · {{ optional($quotation->customer)->name }}
                    · Mode: <strong>{{ $quote->isOverall() ? 'Overall total' : 'Item prices' }}</strong>
                    · Status: <strong>{{ ucfirst($quote->status) }}</strong>
                </p>
            </div>
            <a href="{{ route('quotations.index', ['tab' => 'quoted']) }}" class="btn btn-outline-secondary btn-sm">Back to Client Quotes</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-muted small">Original total</div>
                        <div class="h4 mb-0">{{ number_format((float)$quote->original_grand_total, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Client proposed total</div>
                        <div class="h4 mb-0 text-info">{{ number_format((float)$quote->proposed_grand_total, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Difference</div>
                        @php $diff = (float)$quote->proposed_grand_total - (float)$quote->original_grand_total; @endphp
                        <div class="h4 mb-0 {{ $diff < 0 ? 'text-success' : ($diff > 0 ? 'text-danger' : '') }}">
                            {{ ($diff > 0 ? '+' : '').number_format($diff, 2) }}
                        </div>
                    </div>
                </div>
                @if($quote->client_note)
                    <hr>
                    <div class="text-muted small">Client note</div>
                    <p class="mb-0">{{ $quote->client_note }}</p>
                @endif
            </div>
        </div>

        @if($quote->isPending())
            <form method="POST" action="{{ route('quotation.quote_accept', $quotation->id) }}" id="accept-quote-form">
                @csrf
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">Line comparison (edit to modify before accept)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Original unit</th>
                                    <th>Original total</th>
                                    <th>Proposed / final unit</th>
                                    <th>Proposed total</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($lineViews as $i => $lv)
                                    @php $ql = $lv['quote_line']; @endphp
                                    <tr data-qty="{{ $lv['qty'] }}">
                                        <td>{{ $lv['name'] }}</td>
                                        <td>{{ $lv['qty'] }}</td>
                                        <td>{{ number_format((float)$ql->original_net_unit_price, 2) }}</td>
                                        <td>{{ number_format((float)$ql->original_total, 2) }}</td>
                                        <td>
                                            <input type="hidden" name="lines[{{ $i }}][quote_line_id]" value="{{ $ql->id }}">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm admin-unit"
                                                   name="lines[{{ $i }}][proposed_net_unit_price]"
                                                   value="{{ number_format((float)$ql->proposed_net_unit_price, 2, '.', '') }}"
                                                   style="max-width:140px;">
                                        </td>
                                        <td class="admin-line-total">{{ number_format((float)$ql->proposed_total, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-4 mb-2">
                                <label>Final grand total (optional override)</label>
                                <input type="number" step="0.01" min="0" name="proposed_grand_total" id="admin_grand"
                                       class="form-control"
                                       value="{{ number_format((float)$quote->proposed_grand_total, 2, '.', '') }}">
                                <small class="text-muted">Leave as calculated from lines, or override.</small>
                            </div>
                            <div class="form-group col-md-8 mb-2">
                                <label>Admin note (optional)</label>
                                <input type="text" name="admin_note" class="form-control" placeholder="Internal note">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap" style="gap:10px;">
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Accept this quote, apply amounts, and WhatsApp the client the updated quotation for signature?');">
                        Accept quote &amp; send updated quotation
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('quotation.quote_reject', $quotation->id) }}" class="mt-3">
                @csrf
                <div class="form-group" style="max-width:480px;">
                    <label>Reject note (optional)</label>
                    <input type="text" name="admin_note" class="form-control" placeholder="Why the quote is declined">
                </div>
                <button type="submit" class="btn btn-outline-danger"
                        onclick="return confirm('Reject this client quote and keep original quotation amounts?');">
                    Reject quote (keep original)
                </button>
            </form>
        @else
            <div class="alert alert-secondary mb-0">
                This quote is already <strong>{{ $quote->status }}</strong>.
                @if($quote->admin_note)
                    <br>Admin note: {{ $quote->admin_note }}
                @endif
            </div>
        @endif
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    function recalc() {
        var sum = 0;
        document.querySelectorAll('#accept-quote-form tbody tr').forEach(function (tr) {
            var qty = parseFloat(tr.getAttribute('data-qty') || '0') || 0;
            var input = tr.querySelector('.admin-unit');
            var unit = parseFloat(input && input.value ? input.value : '0') || 0;
            var total = Math.round(unit * qty * 100) / 100;
            sum += total;
            var cell = tr.querySelector('.admin-line-total');
            if (cell) cell.textContent = total.toFixed(2);
        });
        var tax = {{ json_encode((float)($quotation->order_tax ?? 0)) }};
        var ship = {{ json_encode((float)($quotation->shipping_cost ?? 0)) }};
        var disc = {{ json_encode((float)($quotation->order_discount ?? 0)) }};
        var grand = Math.round((sum + tax + ship - disc) * 100) / 100;
        var el = document.getElementById('admin_grand');
        if (el) el.value = grand.toFixed(2);
    }
    document.querySelectorAll('.admin-unit').forEach(function (el) {
        el.addEventListener('input', recalc);
    });
})();
</script>
@endsection

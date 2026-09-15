@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Investments</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        <div class="wm-kpis mb-3">
            <div class="wm-kpi"><div class="lbl">Expected 20%</div><div class="val">{{ number_format($alloc['expected'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Actually invested</div><div class="val">{{ number_format($actual, 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Remaining allocation</div><div class="val">{{ number_format($alloc['remaining'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Estimated value</div><div class="val">{{ number_format($value, 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Gain / Loss</div><div class="val">{{ number_format($gain, 2) }}</div></div>
        </div>
        <div class="wm-card">
            <h5>Add investment</h5>
            <form method="POST" action="{{ route('wealth.investments.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="wm-label">Name *</label><input class="wm-field" name="name" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Type</label><input class="wm-field" name="type" placeholder="Equipment, Property…"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Amount *</label><input class="wm-field" type="number" step="0.01" name="amount_invested" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Date</label><input class="wm-field" type="date" name="invested_on" value="{{ date('Y-m-d') }}"></div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Status</label>
                        <select name="status" class="wm-field">
                            @foreach(['planned','active','matured','sold','cancelled'] as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Company</label>
                        <select name="biller_id" class="wm-field"><option value="">—</option>@foreach($billers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Program</label>
                        <select name="program_id" class="wm-field"><option value="">—</option>@foreach($programs as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Current value</label><input class="wm-field" type="number" step="0.01" name="current_value"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Expected return</label><input class="wm-field" type="number" step="0.01" name="expected_return"></div>
                    <div class="col-md-4 mb-2"><label class="wm-label">Description</label><input class="wm-field" name="description"></div>
                    <div class="col-md-3 mb-2"><label class="wm-label">Document</label><input class="wm-field" type="file" name="document"></div>
                    <div class="col-md-12"><button class="wm-btn" type="submit">Save investment</button></div>
                </div>
            </form>
        </div>
        <div class="wm-card">
            <table class="table wm-table">
                <thead><tr><th>Name</th><th>Type</th><th>Date</th><th>Status</th><th class="text-right">Invested</th><th class="text-right">Value</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->type }}</td>
                        <td>{{ $row->invested_on }}</td>
                        <td>{{ strtoupper($row->status) }}</td>
                        <td class="text-right">{{ number_format($row->amount_invested, 2) }}</td>
                        <td class="text-right">{{ number_format($row->current_value !== null ? $row->current_value : $row->amount_invested, 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('wealth.investments.update', $row->id) }}" class="d-flex" style="gap:6px;align-items:center;">
                                @csrf
                                <select name="status" class="wm-field">
                                    @foreach(['planned','active','matured','sold','cancelled'] as $st)
                                        <option value="{{ $st }}" @if($row->status===$st) selected @endif>{{ $st }}</option>
                                    @endforeach
                                </select>
                                <input class="wm-field" type="number" step="0.01" name="current_value" value="{{ $row->current_value }}" placeholder="Value" style="max-width:110px;">
                                <button class="wm-btn wm-btn-out" type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">No investments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

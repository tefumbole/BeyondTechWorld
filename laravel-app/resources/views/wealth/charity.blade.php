@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Charity</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        <div class="wm-kpis mb-3">
            <div class="wm-kpi"><div class="lbl">Expected 10%</div><div class="val">{{ number_format($alloc['expected'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Charity given</div><div class="val">{{ number_format($given, 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Remaining</div><div class="val">{{ number_format($alloc['remaining'], 2) }}</div></div>
        </div>
        <div class="wm-card">
            <h5>Record charity</h5>
            <form method="POST" action="{{ route('wealth.charity.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="wm-label">Beneficiary *</label><input class="wm-field" name="beneficiary" required></div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Category</label>
                        <select name="subcategory_id" class="wm-field">
                            <option value="">—</option>
                            @foreach($subcategories->where('bucket_id', optional($buckets->firstWhere('code','CHARITY'))->id) as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Amount *</label><input class="wm-field" type="number" step="0.01" name="amount" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Date</label><input class="wm-field" type="date" name="given_on" value="{{ date('Y-m-d') }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Payment method</label><input class="wm-field" name="payment_method"></div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Company</label>
                        <select name="biller_id" class="wm-field"><option value="">—</option>@foreach($billers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Program</label>
                        <select name="program_id" class="wm-field"><option value="">—</option>@foreach($programs as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-4 mb-2"><label class="wm-label">Description</label><input class="wm-field" name="description"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Receipt</label><input class="wm-field" type="file" name="document"></div>
                    <div class="col-md-12"><button class="wm-btn" type="submit">Save</button></div>
                </div>
            </form>
        </div>
        <div class="wm-card">
            <form method="GET" class="mb-2">
                <input type="hidden" name="start_date" value="{{ $filter->startDate }}">
                <input type="hidden" name="end_date" value="{{ $filter->endDate }}">
                <input class="wm-field" style="max-width:280px;display:inline-block;" name="beneficiary" value="{{ request('beneficiary') }}" placeholder="Filter beneficiary">
                <button class="wm-btn" type="submit">Filter</button>
            </form>
            <table class="table wm-table">
                <thead><tr><th>Date</th><th>Beneficiary</th><th>Category</th><th>Program</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->given_on }}</td>
                        <td>{{ $row->beneficiary }}</td>
                        <td>{{ optional($row->subcategory)->name }}</td>
                        <td>{{ optional($row->program)->name }}</td>
                        <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No charity records yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

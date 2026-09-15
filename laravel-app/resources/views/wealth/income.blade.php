@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Income</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        <div class="wm-kpi mb-3" style="max-width:240px;"><div class="lbl">Posted income</div><div class="val">{{ number_format($total, 2) }}</div></div>

        <div class="wm-card">
            <h5>Add manual income</h5>
            <form method="POST" action="{{ route('wealth.income.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="wm-label">Title *</label><input class="wm-field" name="title" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Amount *</label><input class="wm-field" name="amount" type="number" step="0.01" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Date *</label><input class="wm-field" type="date" name="occurred_at" value="{{ date('Y-m-d') }}" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Currency</label><input class="wm-field" name="currency" value="XAF"></div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Customer / payer</label>
                        <select name="customer_id" class="wm-field">
                            <option value="">—</option>
                            @foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Company</label>
                        <select name="biller_id" class="wm-field"><option value="">—</option>@foreach($billers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">User</label>
                        <select name="user_id" class="wm-field"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Category</label>
                        <select name="income_category_id" class="wm-field"><option value="">—</option>@foreach($incomeCategories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Program</label>
                        <select name="program_id" class="wm-field"><option value="">—</option>@foreach($programs as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Payment method</label><input class="wm-field" name="payment_method"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Reference</label><input class="wm-field" name="reference"></div>
                    <div class="col-md-4 mb-2"><label class="wm-label">Description</label><input class="wm-field" name="description"></div>
                    <div class="col-md-3 mb-2"><label class="wm-label">Attachment</label><input class="wm-field" type="file" name="attachment"></div>
                    <div class="col-md-12"><button class="wm-btn" type="submit">Save income</button></div>
                </div>
            </form>
        </div>

        <div class="wm-card">
            <table class="table wm-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Source</th><th>Reference</th><th>Customer</th><th>Company</th>
                        <th>Staff</th><th>Program</th><th>Method</th><th>Status</th><th class="text-right">Amount</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->occurred_at }}</td>
                        <td>{{ strtoupper($row->source_type) }}</td>
                        <td>{{ $row->reference ?: $row->title }}</td>
                        <td>{{ optional($row->customer)->name }}</td>
                        <td>{{ optional($row->biller)->name }}</td>
                        <td>{{ optional($row->user)->name }}</td>
                        <td>{{ optional($row->program)->name }}</td>
                        <td>{{ $row->payment_method }}</td>
                        <td>{{ $row->status }}</td>
                        <td class="text-right">{{ number_format($row->amount, 2) }} {{ $row->currency }}</td>
                        <td><a href="{{ route('wealth.income.show', $row->id) }}">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $rows->links() }}
        </div>
    </div>
</section>
@endsection

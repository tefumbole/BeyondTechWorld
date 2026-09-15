@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Expenses</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')
        @if(($unclassified ?? 0) > 0)
            <div class="alert alert-warning">Unclassified Expenses: {{ $unclassified }} legacy rows have no 70/20/10 category yet.</div>
        @endif

        <div class="wm-card">
            <h5>Add expense</h5>
            <form method="POST" action="{{ route('wealth.expenses.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="wm-label">Title</label><input class="wm-field" name="title"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Amount *</label><input class="wm-field" type="number" step="0.01" name="amount" required></div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Expense category *</label>
                        <select name="expense_category_id" class="wm-field" required>
                            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Warehouse *</label>
                        <select name="warehouse_id" class="wm-field" required>
                            @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Account</label>
                        <select name="account_id" class="wm-field"><option value="">—</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">70 / 20 / 10</label>
                        <select name="allocation_bucket_id" class="wm-field" id="wm-bucket">
                            <option value="">Unclassified</option>
                            @foreach($buckets as $b)<option value="{{ $b->id }}">{{ $b->label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Subcategory</label>
                        <select name="wealth_subcategory_id" class="wm-field" id="wm-sub">
                            <option value="">—</option>
                            @foreach($subcategories as $s)
                                <option value="{{ $s->id }}" data-bucket="{{ $s->bucket_id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Entity</label>
                        <select name="entity_type" class="wm-field">
                            <option value="biller">Company</option>
                            <option value="user">Individual</option>
                            <option value="employee">Staff</option>
                            <option value="program">Program</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Company</label>
                        <select name="biller_id" class="wm-field"><option value="">—</option>@foreach($billers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Program</label>
                        <select name="program_id" class="wm-field"><option value="">—</option>@foreach($programs as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Vendor / payee</label><input class="wm-field" name="payee"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Payment method</label><input class="wm-field" name="payment_method"></div>
                    <div class="col-md-4 mb-2"><label class="wm-label">Notes</label><input class="wm-field" name="note"></div>
                    <div class="col-md-3 mb-2"><label class="wm-label">Receipt</label><input class="wm-field" type="file" name="document"></div>
                    <div class="col-md-12 mb-2">
                        <label><input type="checkbox" name="create_investment" value="1"> Also create Investment register row (20%)</label>
                        <label class="ml-3"><input type="checkbox" name="create_charity" value="1"> Also create Charity register row (10%)</label>
                    </div>
                    <div class="col-md-12"><button class="wm-btn" type="submit">Save expense</button></div>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('wealth.expenses.classify') }}" class="wm-card">
            @csrf
            <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <strong>Bulk assign unclassified</strong>
                <select name="allocation_bucket_id" class="wm-field" style="max-width:220px;">
                    @foreach($buckets as $b)<option value="{{ $b->id }}">{{ $b->label }}</option>@endforeach
                </select>
                <button class="wm-btn" type="submit">Assign selected</button>
                <a class="wm-btn wm-btn-out" href="{{ route('wealth.expenses', ['unclassified' => 1]) }}">Show unclassified only</a>
                <a class="wm-btn wm-btn-out" href="{{ route('expense_categories.index') }}">Expense categories</a>
            </div>
            <table class="table wm-table mt-2">
                <thead>
                    <tr><th></th><th>Date</th><th>Ref</th><th>Title</th><th>Category</th><th>Allocation</th><th>Program</th><th class="text-right">Amount</th></tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td><input type="checkbox" name="expense_ids[]" value="{{ $row->id }}"></td>
                        <td>{{ $row->created_at }}</td>
                        <td>{{ $row->reference_no }}</td>
                        <td>{{ $row->title ?: $row->note }}</td>
                        <td>{{ optional($row->expenseCategory)->name }}</td>
                        <td>@include('wealth.partials.badge', ['bucket' => $buckets->firstWhere('id', $row->allocation_bucket_id)])</td>
                        <td>{{ optional($programs->firstWhere('id', $row->program_id))->name }}</td>
                        <td class="text-right">{{ number_format($row->amount, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            {{ $rows->links() }}
        </form>
    </div>
</section>
@endsection

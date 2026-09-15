@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Wealth Manager Settings</h1>
        @include('wealth.partials.nav')

        <div class="wm-card">
            <h5>Allocation rules</h5>
            <p class="text-muted">Percentages must total 100%. Basis is per company (biller) default when no company override exists.</p>
            <form method="POST" action="{{ route('wealth.settings.rules') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="wm-label">Allocation basis</label>
                        <select name="basis" class="wm-field">
                            <option value="gross" @if(optional($rule)->basis==='gross') selected @endif>Gross income</option>
                            <option value="net" @if(optional($rule)->basis==='net') selected @endif>Net income (after operations spend)</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    @foreach($buckets as $b)
                        @php $line = optional($rule)->lines ? $rule->lines->firstWhere('bucket_id', $b->id) : null; @endphp
                        <div class="col-md-3 mb-2">
                            <label class="wm-label">{{ $b->label }}</label>
                            <input class="wm-field" type="number" step="0.01" name="percent[{{ $b->id }}]" value="{{ $line ? $line->percent : 0 }}">
                        </div>
                    @endforeach
                </div>
                <button class="wm-btn" type="submit">Save allocation rules</button>
            </form>
        </div>

        <div class="wm-card">
            <h5>Financial Health bands &amp; weights</h5>
            <form method="POST" action="{{ route('wealth.settings.health') }}">
                @csrf
                <div class="row">
                    <div class="col-md-2 mb-2"><label class="wm-label">Critical max</label><input class="wm-field" name="critical_max" value="{{ optional($health)->critical_max ?? 39 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Needs attention max</label><input class="wm-field" name="attention_max" value="{{ optional($health)->attention_max ?? 59 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Fair max</label><input class="wm-field" name="fair_max" value="{{ optional($health)->fair_max ?? 74 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Very good max</label><input class="wm-field" name="very_good_max" value="{{ optional($health)->very_good_max ?? 89 }}"></div>
                </div>
                <div class="row">
                    <div class="col-md-2 mb-2"><label class="wm-label">Income vs expense</label><input class="wm-field" name="weight_income_expense" value="{{ optional($health)->weight_income_expense ?? 25 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Cash</label><input class="wm-field" name="weight_cash" value="{{ optional($health)->weight_cash ?? 20 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Operations</label><input class="wm-field" name="weight_operations" value="{{ optional($health)->weight_operations ?? 15 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Investment</label><input class="wm-field" name="weight_investment" value="{{ optional($health)->weight_investment ?? 15 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Charity</label><input class="wm-field" name="weight_charity" value="{{ optional($health)->weight_charity ?? 10 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Savings</label><input class="wm-field" name="weight_savings" value="{{ optional($health)->weight_savings ?? 10 }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Budget</label><input class="wm-field" name="weight_budget" value="{{ optional($health)->weight_budget ?? 5 }}"></div>
                </div>
                <button class="wm-btn" type="submit">Save health settings</button>
            </form>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="wm-card">
                    <h5>Income categories</h5>
                    <ul>@foreach($incomeCategories as $c)<li>{{ $c->name }}</li>@endforeach</ul>
                    <form method="POST" action="{{ route('wealth.settings.category') }}">
                        @csrf
                        <input type="hidden" name="kind" value="income">
                        <div class="d-flex" style="gap:8px;">
                            <input class="wm-field" name="name" placeholder="New income category" required>
                            <button class="wm-btn" type="submit">Add</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-6">
                <div class="wm-card">
                    <h5>Expense subcategories</h5>
                    <form method="POST" action="{{ route('wealth.settings.category') }}">
                        @csrf
                        <input type="hidden" name="kind" value="expense">
                        <div class="row">
                            <div class="col-md-5 mb-2">
                                <select name="bucket_id" class="wm-field" required>
                                    @foreach($buckets as $b)<option value="{{ $b->id }}">{{ $b->label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-5 mb-2"><input class="wm-field" name="name" placeholder="New subcategory" required></div>
                            <div class="col-md-2 mb-2"><button class="wm-btn" type="submit">Add</button></div>
                        </div>
                    </form>
                    <ul class="mt-2 mb-0" style="max-height:220px;overflow:auto;">
                        @foreach($subcategories as $s)
                            <li>{{ optional($buckets->firstWhere('id', $s->bucket_id))->name }} — {{ $s->name }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('wealth.income.sync') }}" class="wm-card">
            @csrf
            <p class="mb-2">Re-scan paid sales into the income ledger. Existing automatic rows are updated, never duplicated.</p>
            <button class="wm-btn" type="submit">Sync paid sales now</button>
        </form>
    </div>
</section>
@endsection
